<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Presentation\Http;

use Cms\Content\Application\Import\WordPressImportClaimConflict;
use Cms\Content\Application\Import\WordPressImportLedger;
use Cms\Content\Application\Import\WordPressImportRunHistory;
use Cms\Content\Application\Import\WordPressRemoteMedia;
use Cms\Content\Application\Import\WordPressRemoteMediaFailure;
use Cms\Content\Application\Import\WordPressStudioMediaBudget;
use Cms\Content\Application\Import\WordPressStudioRestSource;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageSlug;
use Cms\Content\Domain\PageTitle;
use Cms\Content\Infrastructure\Import\DoctrineWordPressImportLedger;
use Cms\Content\Infrastructure\Import\DoctrineWordPressImportRunHistory;
use Cms\Content\Infrastructure\Import\WordPressRemoteMediaDownloader;
use Cms\Content\Infrastructure\Import\WordPressRestReader;
use Cms\Content\Infrastructure\Persistence\DoctrineEntryRepository;
use Cms\Content\Infrastructure\Persistence\DoctrinePageRepository;
use Cms\Content\Infrastructure\Persistence\DoctrineRelationTargetLookup;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Cms\Identity\Infrastructure\Security\SecurityUserProvider;
use Cms\Media\Application\ImageProcessor;
use Cms\Media\Application\MediaAssetView;
use Cms\Media\Application\ProcessedImage;
use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaVariant;
use Cms\Media\Infrastructure\Image\LocalGdImageProcessor;
use Cms\Media\Infrastructure\Persistence\DoctrineMediaAssetRepository;
use Cms\Schema\Application\RelationTargetLookup;
use Cms\Schema\Infrastructure\Persistence\DoctrineContentTypeRepository;
use Cms\Settings\Infrastructure\Persistence\DoctrineSiteSettingsRepository;
use Cms\Taxonomy\Infrastructure\Persistence\DoctrineTaxonomyRepository;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Content\InMemoryPageRepository;
use Cms\Tests\Media\InMemoryMediaAssetRepository;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use Cms\Tests\Settings\InMemorySiteSettingsRepository;
use Cms\Tests\Taxonomy\InMemoryTaxonomyRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @phpstan-import-type Attachment from \Cms\Content\Application\Import\ImportWordPressMedia */
final class WordPressImportControllerTest extends WebTestCase
{
    private const string ENDPOINT = '/api/v1/admin/imports/wordpress';
    private const string REST_ENDPOINT = self::ENDPOINT.'/rest';
    private const string REST_URL = 'https://source.example.test/wp-json/wp/v2';
    private const string REST_IMAGE_URL = 'https://source.example.test/wp-content/uploads/photo.jpg';
    private const string RUN_ID = 'b0c1db43-027e-4d77-9d81-3804bcec4b25';
    private const string EXPORT = <<<'XML'
        <rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/">
          <channel>
            <wp:wxr_version>1.2</wp:wxr_version>
            <item>
              <title>Studio import</title>
              <wp:post_id>12</wp:post_id>
              <wp:post_name>studio-import</wp:post_name>
              <wp:post_type>page</wp:post_type>
              <wp:status>draft</wp:status>
            </item>
          </channel>
        </rss>
        XML;

    public function testAnonymousImportRequiresAuthentication(): void
    {
        $client = self::createClient();
        $client->request('POST', self::ENDPOINT, server: $this->multipartHeaders($client));

        self::assertResponseStatusCodeSame(401);
    }

    public function testEditorCannotImportEvenWithCsrfToken(): void
    {
        $client = $this->authenticatedClient(['ROLE_EDITOR']);
        $client->request('POST', self::ENDPOINT, server: $this->multipartHeaders($client));

        self::assertResponseStatusCodeSame(403);
        self::assertJsonStringEqualsJsonString('{"error":"Owner access required."}', (string) $client->getResponse()->getContent());
    }

    public function testRestImportRequiresCsrfAndAnOwnerSession(): void
    {
        $client = self::createClient();
        $client->request('POST', self::REST_ENDPOINT, server: $this->restHeaders($client), content: '{}');
        self::assertResponseStatusCodeSame(401);

        self::ensureKernelShutdown();
        $client = $this->authenticatedClient(['ROLE_EDITOR']);
        $client->request('POST', self::REST_ENDPOINT, server: $this->restHeaders($client), content: '{}');
        self::assertResponseStatusCodeSame(403);
        self::assertJsonStringEqualsJsonString('{"error":"Owner access required."}', (string) $client->getResponse()->getContent());

        self::ensureKernelShutdown();
        $client = $this->authenticatedClient();
        $client->request('POST', self::REST_ENDPOINT, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(403);
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertJsonStringEqualsJsonString('{"error":"Invalid CSRF token."}', (string) $client->getResponse()->getContent());
    }

    public function testRestImportRejectsInvalidBodiesBeforeReadingTheSource(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->restHeaders($client);
        $rest = $this->createMock(WordPressStudioRestSource::class);
        $rest->expects(self::never())->method('readPublic');
        self::getContainer()->set(WordPressRestReader::class, $rest);

        $payload = ['apiUrl' => self::REST_URL, 'source' => 'rest-site', 'mode' => 'dry-run'];
        foreach ([
            '{',
            '[]',
            json_encode($payload + ['credentials' => 'unsupported'], JSON_THROW_ON_ERROR),
            json_encode($payload + ['downloadMedia' => 'true'], JSON_THROW_ON_ERROR),
            json_encode($payload + ['downloadMedia' => null], JSON_THROW_ON_ERROR),
            json_encode($payload + ['selectedIds' => '12'], JSON_THROW_ON_ERROR),
            json_encode(['mode' => 'apply', 'selectedIds' => '12,12'] + $payload, JSON_THROW_ON_ERROR),
            json_encode(['mode' => 'apply', 'selectedIds' => null] + $payload, JSON_THROW_ON_ERROR),
            json_encode(['source' => 'Invalid key'] + $payload, JSON_THROW_ON_ERROR),
            json_encode(['apiUrl' => str_repeat('x', 513)] + $payload, JSON_THROW_ON_ERROR),
        ] as $body) {
            $client->request('POST', self::REST_ENDPOINT, server: $headers, content: $body);
            self::assertResponseStatusCodeSame(422);
        }
        $client->request('POST', self::REST_ENDPOINT, server: ['CONTENT_TYPE' => 'text/plain'] + $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', self::REST_ENDPOINT, server: ['CONTENT_LENGTH' => '131073'] + $headers, content: '{}');
        self::assertResponseStatusCodeSame(413);
        $client->request('POST', self::REST_ENDPOINT, server: ['CONTENT_LENGTH' => '2'] + $headers, content: str_repeat(' ', 128 * 1024 + 1));
        self::assertResponseStatusCodeSame(413);
    }

    public function testRestImportRejectsInvalidMediaHostsBeforeReadingTheSource(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->restHeaders($client);
        $rest = $this->createMock(WordPressStudioRestSource::class);
        $rest->expects(self::never())->method('readPublic');
        self::getContainer()->set(WordPressRestReader::class, $rest);

        $payload = ['apiUrl' => self::REST_URL, 'source' => 'rest-site', 'mode' => 'dry-run'];
        foreach ([
            [false, [], 'Allowed media hosts require public image downloads.'],
            [false, ['cdn.example.test'], 'Allowed media hosts require public image downloads.'],
            [true, 'cdn.example.test', 'Provide a list of up to eight WordPress media hostnames.'],
            [true, null, 'Provide a list of up to eight WordPress media hostnames.'],
            [true, (object) [], 'Provide a list of up to eight WordPress media hostnames.'],
            [true, [42], 'Provide a list of up to eight WordPress media hostnames.'],
            [true, array_fill(0, 9, 'cdn.example.test'), 'Provide a list of up to eight WordPress media hostnames.'],
            [true, ['https://cdn.example.test'], 'WordPress media hosts must be exact public DNS hostnames.'],
            [true, ['cdn.example.test:443'], 'WordPress media hosts must be exact public DNS hostnames.'],
            [true, ['*.example.test'], 'WordPress media hosts must be exact public DNS hostnames.'],
            [true, ['cdn.internal'], 'WordPress media hosts must be exact public DNS hostnames.'],
        ] as [$enabled, $hosts, $error]) {
            $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode($payload + [
                'downloadMedia' => $enabled,
                'allowedMediaHosts' => $hosts,
            ], JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(422);
            self::assertJsonStringEqualsJsonString(json_encode(['error' => $error], JSON_THROW_ON_ERROR), (string) $client->getResponse()->getContent());
        }
    }

    public function testRestImportHandlesUnsafeUrlsAndRemoteFailuresWithoutExposingDetails(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->restHeaders($client);
        $rest = $this->createMock(WordPressStudioRestSource::class);
        $rest->expects(self::exactly(2))->method('readPublic')->willReturnCallback(static function (string $url): never {
            if ($url === self::REST_URL) {
                throw new \RuntimeException('Private diagnostic from the upstream.');
            }

            throw new \InvalidArgumentException('Private validation details.');
        });
        self::getContainer()->set(WordPressRestReader::class, $rest);
        $ledger = $this->createMock(WordPressImportLedger::class);
        $ledger->expects(self::never())->method('transactional');
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);
        $payload = ['source' => 'rest-site', 'mode' => 'apply'];
        $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode(['apiUrl' => 'http://source.example.test/wp-json/wp/v2'] + $payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
        self::assertJsonStringEqualsJsonString('{"error":"Provide a safe HTTPS WordPress REST API URL."}', (string) $client->getResponse()->getContent());
        $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode(['apiUrl' => self::REST_URL] + $payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(502);
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertJsonStringEqualsJsonString('{"error":"Could not read the public WordPress REST API."}', (string) $client->getResponse()->getContent());
    }

    public function testRestImportPreviewsAppliesAndReplaysWithSharedHistory(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->restHeaders($client);
        $baseItem = [
            'type' => 'page', 'status' => 'publish', 'html' => '<p>Public material</p>', 'link' => '', 'passwordProtected' => false,
            'terms' => [], 'thumbnailId' => null, 'authorLogin' => null,
        ];
        $rest = $this->createMock(WordPressStudioRestSource::class);
        $rest->expects(self::exactly(4))->method('readPublic')->with(self::REST_URL)->willReturn([
            'items' => [
                ['id' => '12', 'title' => 'Keep this page', 'slug' => 'keep-this-page'] + $baseItem,
                ['id' => '13', 'title' => 'Omit this page', 'slug' => 'omit-this-page'] + $baseItem,
            ],
            'attachments' => [['id' => '20', 'eligible' => true, 'url' => 'https://media.example.test/photo.jpg', 'path' => 'photo.jpg', 'alt' => '']],
            'terms' => [],
            'authors' => [],
            'skippedTypes' => [],
            'sourceUrl' => 'https://source.example.test',
        ]);
        self::getContainer()->set(WordPressRestReader::class, $rest);
        $pages = new InMemoryPageRepository();
        self::getContainer()->set(DoctrinePageRepository::class, $pages);
        self::getContainer()->set(DoctrineContentTypeRepository::class, new InMemoryContentTypeRepository());
        self::getContainer()->set(DoctrineSiteSettingsRepository::class, new InMemorySiteSettingsRepository());
        $ledger = $this->createMock(WordPressImportLedger::class);
        /** @var array<string,true> $claimed */
        $claimed = [];
        $ledger->method('contains')->willReturnCallback(static function (string $source, string $id) use (&$claimed): bool {
            return isset($claimed[$source.':'.$id]);
        });
        $ledger->expects(self::once())->method('claim')->with('rest-site', '12')->willReturnCallback(static function (string $source, string $id) use (&$claimed): void {
            $claimed[$source.':'.$id] = true;
        });
        $ledger->expects(self::never())->method('recordMediaAsset');
        $ledger->expects(self::exactly(2))->method('transactional')->willReturnCallback(static fn (\Closure $operation): mixed => $operation());
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);
        $history = $this->createMock(WordPressImportRunHistory::class);
        $history->expects(self::exactly(2))->method('record')->willReturnCallback(static function (array $report): void {
            self::assertSame('rest', $report['input']);
            self::assertSame('apply', $report['mode']);
        });
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);

        $payload = ['apiUrl' => self::REST_URL, 'source' => 'rest-site'];
        $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode($payload + ['mode' => 'dry-run'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(<<<'JSON'
            {"mode":"dry-run","input":"rest","source":"rest-site","counts":{"planned":2},"skippedTypes":{},
            "items":[
              {"id":"12","type":"page","status":"publish","title":"Keep this page","sourcePath":null,"targetPath":"/keep-this-page","state":"planned","redirect":"none","images":{"inline":0,"missingInline":0,"featured":"none"}},
              {"id":"13","type":"page","status":"publish","title":"Omit this page","sourcePath":null,"targetPath":"/omit-this-page","state":"planned","redirect":"none","images":{"inline":0,"missingInline":0,"featured":"none"}}
            ],"media":[{"id":"20","state":"media_directory_required"}],"taxonomy":{"terms":[],"assignments":[]},"authors":[]}
            JSON, (string) $client->getResponse()->getContent());
        self::assertSame([], array_column($pages->all(), 'id'));

        $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode($payload + ['mode' => 'apply', 'selectedIds' => '12,99'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
        self::assertSame([], array_column($pages->all(), 'id'));

        foreach (['imported', 'already_imported'] as $state) {
            $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode($payload + ['mode' => 'apply', 'selectedIds' => '12'], JSON_THROW_ON_ERROR));
            self::assertResponseIsSuccessful();
            $report = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($report);
            self::assertSame('rest', $report['input']);
            self::assertSame('selection', $report['scope']);
            self::assertSame([$state => 1], $report['counts']);
            self::assertIsArray($report['items']);
            self::assertIsArray($report['items'][0]);
            self::assertCount(1, $pages->all());
            if ($state === 'imported') {
                self::assertSame(['kind' => 'page', 'id' => $pages->all()[0]->id->value], $report['items'][0]['draft']);
            } else {
                self::assertArrayNotHasKey('draft', $report['items'][0]);
            }
        }
    }

    public function testRestMediaDryRunApplyAndReplay(): void
    {
        $this->assertRestMediaDryRunApplyAndReplay(self::REST_IMAGE_URL);
    }

    public function testRestMediaAllowsExplicitCdnHostsWithoutChangingReplay(): void
    {
        $this->assertRestMediaDryRunApplyAndReplay('https://cdn.example.test/wp-content/uploads/photo.jpg', 'cdn.example.test');
    }

    private function assertRestMediaDryRunApplyAndReplay(string $imageUrl, ?string $cdnHost = null): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->restHeaders($client);
        $pages = $this->useRestMediaSnapshot(imageUrl: $imageUrl);
        $ledger = $this->createMock(WordPressImportLedger::class);
        /** @var array<string,true> $claimed */
        $claimed = [];
        $asset = null;
        $ledger->method('transactional')->willReturnCallback(static fn (\Closure $operation): mixed => $operation());
        $ledger->method('contains')->willReturnCallback(static function (string $source, string $id) use (&$claimed): bool {
            return isset($claimed[$id]);
        });
        $ledger->expects(self::exactly(2))->method('claim')->willReturnCallback(static function (string $source, string $id) use (&$claimed): void {
            self::assertSame('rest-media', $source);
            $claimed[$id] = true;
        });
        $ledger->expects(self::once())->method('recordMediaAsset')->willReturnCallback(static function (string $source, string $id, string $assetId) use (&$asset): void {
            self::assertSame('rest-media', $source);
            self::assertSame('20', $id);
            $asset = $assetId;
        });
        $ledger->method('mediaAssetId')->willReturnCallback(static function (string $source, string $id) use (&$asset): ?string {
            return $id === '20' ? $asset : null;
        });
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);

        $history = $this->createMock(WordPressImportRunHistory::class);
        $history->expects(self::exactly(2))->method('record')->willReturnCallback(static function (array $report): void {
            self::assertArrayNotHasKey('allowedMediaHosts', $report);
        });
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);

        $allowedHosts = $cdnHost === null ? [] : [$cdnHost];
        $urlPolicy = new WordPressRemoteMediaDownloader();
        $remote = $this->createMock(WordPressRemoteMedia::class);
        $remote->expects(self::exactly($cdnHost === null ? 2 : 3))->method('assertUrl')
            ->willReturnCallback(static function (string $apiUrl, string $url, array $hosts) use ($imageUrl, $allowedHosts, $urlPolicy): void {
                self::assertSame(self::REST_URL, $apiUrl);
                self::assertSame($imageUrl, $url);
                /** @var list<string> $hosts */
                $urlPolicy->assertUrl($apiUrl, $url, $hosts);
                self::assertSame($allowedHosts, $hosts);
            });
        $remote->expects(self::once())->method('download')
            ->with(self::REST_URL, $imageUrl, self::isInstanceOf(\Closure::class), $allowedHosts, null, self::isInstanceOf(WordPressStudioMediaBudget::class))
            ->willReturnCallback(static fn (string $apiUrl, string $url, \Closure $consume): MediaAssetView => self::consumeRestImage($apiUrl, $url, $consume, $imageUrl));
        self::getContainer()->set(WordPressRemoteMediaDownloader::class, $remote);
        $this->useTestImageProcessor(reverted: false);

        $request = ['apiUrl' => self::REST_URL, 'source' => 'rest-media', 'downloadMedia' => true];
        $defaultRequest = $request;
        if ($cdnHost !== null) {
            $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode($request + ['mode' => 'dry-run'], JSON_THROW_ON_ERROR));
            self::assertResponseIsSuccessful();
            $unlisted = $this->decodeRestMediaReport($client);
            self::assertSame(['id' => '20', 'state' => 'invalid', 'reason' => 'The WordPress attachment host is not allowed.'], $unlisted['media'][0]);
            self::assertSame(['inline' => 0, 'missingInline' => 1, 'featured' => 'missing'], $unlisted['items'][0]['images']);
            $request['allowedMediaHosts'] = [strtoupper($cdnHost), $cdnHost];
        }
        $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode($request + ['mode' => 'dry-run'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $preview = $this->decodeRestMediaReport($client);
        self::assertSame(['id' => '20', 'state' => 'planned'], $preview['media'][0]);
        self::assertSame(['inline' => 1, 'missingInline' => 0, 'featured' => 'linked'], $preview['items'][0]['images']);
        self::assertSame([], array_column($pages->all(), 'id'));

        foreach (['imported', 'already_imported'] as $state) {
            $applyRequest = $state === 'imported' ? $request : $defaultRequest;
            $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode($applyRequest + ['mode' => 'apply', 'selectedIds' => '12'], JSON_THROW_ON_ERROR));
            self::assertResponseIsSuccessful();
            $report = $this->decodeRestMediaReport($client);
            self::assertSame([$state => 1], $report['counts']);
            self::assertSame($state, $report['media'][0]['state']);
            self::assertIsString($report['media'][0]['assetId']);
            self::assertCount(1, $pages->all());
            $page = $pages->all()[0];
            self::assertSame($report['media'][0]['assetId'], $page->seo->openGraphImageAssetId);
            self::assertSame($report['media'][0]['assetId'], $page->blocks[1]->data['assetId']);
        }
    }

    public function testRestMediaTransferFailureAndHistoryFailureCompensateStagedImages(): void
    {
        foreach ([false, true] as $failOnHistory) {
            self::ensureKernelShutdown();
            $client = $this->authenticatedClient();
            $headers = $this->restHeaders($client);
            $this->useRestMediaSnapshot($failOnHistory ? [] : [
                ['id' => '21', 'eligible' => true, 'url' => 'https://source.example.test/other.jpg', 'path' => 'other.jpg', 'alt' => ''],
            ]);
            $ledger = $this->createStub(WordPressImportLedger::class);
            $transactionDepth = 0;
            $ledger->method('transactional')->willReturnCallback(static function (\Closure $operation) use (&$transactionDepth): mixed {
                ++$transactionDepth;
                try {
                    return $operation();
                } finally {
                    --$transactionDepth;
                }
            });
            self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);

            $history = $this->createMock(WordPressImportRunHistory::class);
            $history->expects($failOnHistory ? self::once() : self::never())->method('record')->willReturnCallback(static function () use (&$transactionDepth): never {
                self::assertSame(1, $transactionDepth);
                throw new \RuntimeException('Failed to persist report.');
            });
            self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);

            $remote = $this->createMock(WordPressRemoteMedia::class);
            $remote->expects($failOnHistory ? self::once() : self::exactly(2))->method('download')->willReturnCallback(
                static function (string $apiUrl, string $attachmentUrl, \Closure $consume): MediaAssetView {
                    if ($attachmentUrl !== self::REST_IMAGE_URL) {
                        throw new WordPressRemoteMediaFailure('Sensitive remote HTTP failure details.');
                    }

                    return self::consumeRestImage($apiUrl, $attachmentUrl, $consume);
                },
            );
            self::getContainer()->set(WordPressRemoteMediaDownloader::class, $remote);
            $this->useTestImageProcessor(reverted: true);

            $client->request('POST', self::REST_ENDPOINT, server: $headers, content: json_encode([
                'apiUrl' => self::REST_URL,
                'source' => 'rest-media',
                'mode' => 'apply',
                'downloadMedia' => true,
            ], JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame($failOnHistory ? 500 : 502);
            if (!$failOnHistory) {
                self::assertJsonStringEqualsJsonString('{"error":"Could not read the public WordPress REST API."}', (string) $client->getResponse()->getContent());
            }
        }
    }

    public function testHistoryRequiresOwnerOnBothRoutes(): void
    {
        $client = self::createClient();
        $client->request('GET', self::ENDPOINT.'/runs');
        self::assertResponseStatusCodeSame(401);
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $client->request('GET', self::ENDPOINT.'/runs/'.self::RUN_ID);
        self::assertResponseStatusCodeSame(401);
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));

        self::ensureKernelShutdown();
        $client = $this->authenticatedClient(['ROLE_EDITOR']);
        $history = $this->createMock(WordPressImportRunHistory::class);
        $history->expects(self::never())->method('latest');
        $history->expects(self::never())->method('report');
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);
        foreach (['/runs', '/runs/'.self::RUN_ID] as $route) {
            $client->request('GET', self::ENDPOINT.$route);
            self::assertResponseStatusCodeSame(403);
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        }
    }

    public function testHistoryPaginatesAndReopensReport(): void
    {
        $client = $this->authenticatedClient();
        $summary = [
            'id' => self::RUN_ID,
            'source' => 'legacy-site',
            'scope' => 'selection',
            'completedAt' => '2026-11-20T12:13:14.000+00:00',
            'counts' => (object) ['imported' => 1],
        ];
        $reportJson = '{"mode":"apply","input":"wxr","source":"legacy-site","scope":"selection","counts":{},"skippedTypes":{},"items":[],"media":[],"taxonomy":{"terms":[],"assignments":[]},"authors":[]}';
        $report = json_decode($reportJson, flags: JSON_THROW_ON_ERROR);
        self::assertInstanceOf(\stdClass::class, $report);
        $history = $this->createMock(WordPressImportRunHistory::class);
        $history->expects(self::exactly(2))->method('latest')->willReturnCallback(static function (int $offset, int $limit) use ($summary): array {
            self::assertSame(21, $limit);
            self::assertContains($offset, [0, 20]);

            return $offset === 0 ? array_fill(0, 21, $summary) : [$summary];
        });
        $history->expects(self::once())->method('report')->with(self::RUN_ID)->willReturn($report);
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);

        $client->request('GET', self::ENDPOINT.'/runs');
        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertJsonStringEqualsJsonString(json_encode([
            'items' => array_fill(0, 20, $summary),
            'page' => 1,
            'hasMore' => true,
        ], JSON_THROW_ON_ERROR), (string) $client->getResponse()->getContent());
        $client->request('GET', self::ENDPOINT.'/runs?page=2');
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(json_encode([
            'items' => [$summary],
            'page' => 2,
            'hasMore' => false,
        ], JSON_THROW_ON_ERROR), (string) $client->getResponse()->getContent());
        $client->request('GET', self::ENDPOINT.'/runs/'.self::RUN_ID);
        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSame($reportJson, $client->getResponse()->getContent());
    }

    public function testHistoryRejectsInvalidPageAndUnknownRun(): void
    {
        $client = $this->authenticatedClient();
        $history = $this->createMock(WordPressImportRunHistory::class);
        $history->expects(self::never())->method('latest');
        $history->expects(self::once())->method('report')->with(self::RUN_ID)->willReturn(null);
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);

        foreach (['page=0', 'page=1000001', 'page=1.5', 'page[]=-1'] as $query) {
            $client->request('GET', self::ENDPOINT.'/runs?'.$query);
            self::assertResponseStatusCodeSame(422);
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        }
        foreach (['invalid-id', self::RUN_ID] as $id) {
            $client->request('GET', self::ENDPOINT.'/runs/'.$id);
            self::assertResponseStatusCodeSame(404);
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        }
    }

    public function testOwnerNeedsCsrfToken(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', self::ENDPOINT, ['source' => 'legacy-site', 'mode' => 'apply', 'selectedIds' => '12'], server: ['CONTENT_TYPE' => 'multipart/form-data; boundary=wxr']);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonStringEqualsJsonString('{"error":"Invalid CSRF token."}', (string) $client->getResponse()->getContent());
    }

    public function testInvalidSelectionIsRejectedBeforeStartingTheImport(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->multipartHeaders($client);
        $ledger = $this->createMock(WordPressImportLedger::class);
        $ledger->expects(self::never())->method('transactional');
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);

        $file = tmpfile();
        self::assertIsResource($file);
        try {
            fwrite($file, self::EXPORT);
            foreach (['', '0', '012', '12,12', '12, 13', '12,', '12,99', '123456789012345678901', str_repeat('1,', 5000).'1', ['12']] as $selection) {
                $client->request('POST', self::ENDPOINT, ['source' => 'legacy-site', 'mode' => 'apply', 'selectedIds' => $selection], ['file' => $this->upload($file)], $headers);
                self::assertResponseStatusCodeSame(422);
            }
            $client->request('POST', self::ENDPOINT, ['source' => 'legacy-site', 'mode' => 'dry-run', 'selectedIds' => '12'], ['file' => $this->upload($file)], $headers);
            self::assertResponseStatusCodeSame(422);
        } finally {
            fclose($file);
        }
    }

    public function testAppliesSelectedItemsAndDependenciesInExportOrderAndReplays(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->multipartHeaders($client);
        $pages = new InMemoryPageRepository();
        $entries = new InMemoryEntryRepository();
        $taxonomies = new InMemoryTaxonomyRepository();
        $media = new InMemoryMediaAssetRepository();
        $assetId = MediaAssetId::new();
        $variant = new MediaVariant(480, 240, 10, MediaVariant::urlFor($assetId, 480, false));
        $media->add(MediaAsset::create($assetId, 'mapped.webp', 480, 240, 10, '', [$variant], new \DateTimeImmutable()));
        $ledger = $this->createMock(WordPressImportLedger::class);
        /** @var array<string,true> $claimed */
        $claimed = ['legacy-site:20' => true];
        $ledger->method('contains')->willReturnCallback(static function (string $source, string $id) use (&$claimed): bool {
            return isset($claimed[$source.':'.$id]);
        });
        $ledger->expects(self::exactly(2))->method('claim')->willReturnCallback(static function (string $source, string $id) use (&$claimed): void {
            $claimed[$source.':'.$id] = true;
        });
        $ledger->expects(self::once())->method('recordAuthor');
        $ledger->expects(self::never())->method('recordMediaAsset');
        $ledger->method('mediaAssetId')->with('legacy-site', '20')->willReturn($assetId->value);
        $transactionDepth = 0;
        $ledger->method('transactional')->willReturnCallback(static function (\Closure $operation) use (&$transactionDepth): mixed {
            ++$transactionDepth;
            try {
                return $operation();
            } finally {
                --$transactionDepth;
            }
        });
        $history = $this->createMock(WordPressImportRunHistory::class);
        /** @var list<string> $recorded */
        $recorded = [];
        $history->expects(self::exactly(2))->method('record')->willReturnCallback(static function (array $report) use (&$recorded, &$transactionDepth): void {
            self::assertSame(1, $transactionDepth);
            $recorded[] = json_encode($report, JSON_THROW_ON_ERROR);
        });
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);
        self::getContainer()->set(DoctrinePageRepository::class, $pages);
        self::getContainer()->set(DoctrineEntryRepository::class, $entries);
        $relations = $this->createStub(RelationTargetLookup::class);
        $relations->method('exists')->willReturnCallback(static fn (string $entryId, string $typeId): bool => $entries->get(EntryId::fromString($entryId))?->contentTypeId === $typeId);
        self::getContainer()->set(DoctrineRelationTargetLookup::class, $relations);
        self::getContainer()->set(DoctrineContentTypeRepository::class, new InMemoryContentTypeRepository());
        self::getContainer()->set(DoctrineSiteSettingsRepository::class, new InMemorySiteSettingsRepository());
        self::getContainer()->set(DoctrineTaxonomyRepository::class, $taxonomies);
        self::getContainer()->set(DoctrineMediaAssetRepository::class, $media);

        $file = tmpfile();
        self::assertIsResource($file);
        try {
            $additionalItems = <<<'XML'
                <wp:author><wp:author_login>selected</wp:author_login><wp:author_display_name>Selected author</wp:author_display_name></wp:author>
                <wp:author><wp:author_login>omitted</wp:author_login><wp:author_display_name>Omitted author</wp:author_display_name></wp:author>
                <wp:category><wp:category_nicename>child</wp:category_nicename><wp:cat_name>Child</wp:cat_name><wp:category_parent>parent</wp:category_parent></wp:category>
                <wp:category><wp:category_nicename>parent</wp:category_nicename><wp:cat_name>Parent</wp:cat_name></wp:category>
                <item xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
                  <title>Selected post</title>
                  <wp:post_id>99999999999999999999</wp:post_id>
                  <wp:post_name>selected-post</wp:post_name>
                  <wp:post_type>post</wp:post_type>
                  <wp:status>draft</wp:status>
                  <dc:creator>selected</dc:creator>
                  <category domain="category" nicename="child">Child</category>
                  <content:encoded><![CDATA[<img src="https://source.example.test/uploads/photo-200x100.jpg">]]></content:encoded>
                  <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>20</wp:meta_value></wp:postmeta>
                </item>
                <item xmlns:dc="http://purl.org/dc/elements/1.1/">
                  <title>Omitted post</title>
                  <wp:post_id>13</wp:post_id>
                  <wp:post_type>post</wp:post_type>
                  <wp:status>draft</wp:status>
                  <dc:creator>omitted</dc:creator>
                  <category domain="category" nicename="unrelated">Unrelated</category>
                </item>
                <item><wp:post_id>14</wp:post_id><wp:post_type>product</wp:post_type></item>
                <item>
                  <wp:post_id>20</wp:post_id>
                  <wp:post_type>attachment</wp:post_type>
                  <wp:status>inherit</wp:status>
                  <wp:attachment_url>https://source.example.test/uploads/photo.jpg</wp:attachment_url>
                </item>
                XML;
            fwrite($file, str_replace('</channel>', $additionalItems.'</channel>', self::EXPORT));
            $fields = ['source' => 'legacy-site', 'mode' => 'apply', 'selectedIds' => '99999999999999999999,12'];
            $client->request('POST', self::ENDPOINT, $fields, ['file' => $this->upload($file)], $headers);

            self::assertResponseIsSuccessful();
            self::assertJsonStringEqualsJsonString($recorded[0], (string) $client->getResponse()->getContent());
            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($response);
            self::assertSame('selection', $response['scope']);
            self::assertSame(['imported' => 2], $response['counts']);
            self::assertSame([], $response['skippedTypes']);
            self::assertIsArray($response['items']);
            self::assertIsArray($response['items'][0]);
            self::assertIsArray($response['items'][1]);
            self::assertSame('12', $response['items'][0]['id']);
            self::assertSame('99999999999999999999', $response['items'][1]['id']);
            self::assertSame(['Studio import'], array_map(static fn (Page $page): string => $page->title->value, $pages->all()));
            self::assertSame(['Selected author', 'Selected post'], array_column($entries->all(), 'title'));
            self::assertSame([
                'kind' => 'page',
                'id' => $pages->all()[0]->id->value,
            ], $response['items'][0]['draft']);
            $post = $entries->all()[1];
            self::assertSame([
                'kind' => 'entry',
                'id' => $post->id->value,
                'contentTypeId' => $post->contentTypeId,
            ], $response['items'][1]['draft']);
            self::assertSame(['parent', 'child'], array_column(array_values($taxonomies->termItems), 'slug'));
            self::assertSame(['child'], array_column($taxonomies->entryTerms($post->id->value), 'slug'));
            self::assertSame($assetId->value, $post->values['wordpressFeaturedImage']);
            self::assertSame($assetId->value, $post->values['wordpressInlineImage1']);

            $client->request('POST', self::ENDPOINT, $fields, ['file' => $this->upload($file)], $headers);
            self::assertResponseIsSuccessful();
            self::assertJsonStringEqualsJsonString($recorded[1], (string) $client->getResponse()->getContent());
            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($response);
            self::assertSame(['already_imported' => 2], $response['counts']);
            self::assertIsArray($response['items']);
            self::assertIsArray($response['items'][0]);
            self::assertIsArray($response['items'][1]);
            self::assertArrayNotHasKey('draft', $response['items'][0]);
            self::assertArrayNotHasKey('draft', $response['items'][1]);
            self::assertSame([], $response['authors']);
            self::assertSame(['terms' => [], 'assignments' => []], $response['taxonomy']);
            self::assertCount(1, $pages->all());
            self::assertCount(2, $entries->all());
        } finally {
            fclose($file);
        }
    }

    public function testMalformedUploadAndUnexpectedFieldsAreRejected(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->multipartHeaders($client);
        $file = tmpfile();
        self::assertIsResource($file);
        try {
            fwrite($file, '<rss');
            $upload = $this->upload($file);
            $fields = ['source' => 'legacy-site', 'mode' => 'dry-run'];
            $client->request('POST', self::ENDPOINT, $fields, ['file' => $upload], $headers);
            self::assertResponseStatusCodeSame(422);
            self::assertJsonStringEqualsJsonString('{"error":"The WordPress export contains invalid XML."}', (string) $client->getResponse()->getContent());

            $client->request('POST', self::ENDPOINT, $fields + ['mediaDirectory' => '/uploads'], ['file' => $upload], $headers);
            self::assertResponseStatusCodeSame(422);
            self::assertJsonStringEqualsJsonString('{"error":"Provide a WXR file, source key, and dry-run or apply mode."}', (string) $client->getResponse()->getContent());
        } finally {
            fclose($file);
        }
    }

    public function testOversizedRequestAndFileAreRejected(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->multipartHeaders($client);
        $client->request('POST', self::ENDPOINT, server: $headers + ['CONTENT_LENGTH' => (string) (9 * 1024 * 1024 + 1)]);
        self::assertResponseStatusCodeSame(413);

        $upload = new UploadedFile('', 'wordpress.xml', error: UPLOAD_ERR_INI_SIZE, test: true);
        $client->request('POST', self::ENDPOINT, ['source' => 'legacy-site', 'mode' => 'dry-run'], ['file' => $upload], $headers);
        self::assertResponseStatusCodeSame(413);
        self::assertJsonStringEqualsJsonString('{"error":"The WordPress export exceeds the 8 MiB limit."}', (string) $client->getResponse()->getContent());

        $file = tmpfile();
        self::assertIsResource($file);
        try {
            self::assertTrue(ftruncate($file, 8 * 1024 * 1024 + 1));
            $client->request('POST', self::ENDPOINT, ['source' => 'legacy-site', 'mode' => 'dry-run'], ['file' => $this->upload($file)], $headers);
            self::assertResponseStatusCodeSame(413);
        } finally {
            fclose($file);
        }
    }

    public function testOwnerDryRunReturnsReportWithoutWrites(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->multipartHeaders($client);
        $pages = new InMemoryPageRepository();
        $ledger = $this->createMock(WordPressImportLedger::class);
        $ledger->expects(self::once())->method('contains')->with('legacy-site', '12')->willReturn(false);
        $ledger->expects(self::never())->method('claim');
        $ledger->expects(self::never())->method('transactional');
        $history = $this->createMock(WordPressImportRunHistory::class);
        $history->expects(self::never())->method('record');
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);
        self::getContainer()->set(DoctrinePageRepository::class, $pages);
        self::getContainer()->set(DoctrineContentTypeRepository::class, new InMemoryContentTypeRepository());
        self::getContainer()->set(DoctrineSiteSettingsRepository::class, new InMemorySiteSettingsRepository());

        $file = tmpfile();
        self::assertIsResource($file);
        try {
            fwrite($file, self::EXPORT);
            $upload = $this->upload($file);
            $client->request('POST', self::ENDPOINT, ['source' => 'legacy-site', 'mode' => 'dry-run'], ['file' => $upload], $headers);

            self::assertResponseIsSuccessful();
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
            self::assertJsonStringEqualsJsonString(<<<'JSON'
                {
                  "mode":"dry-run",
                  "input":"wxr",
                  "source":"legacy-site",
                  "counts":{"planned":1},
                  "skippedTypes":{},
                  "items":[{
                    "id":"12",
                    "type":"page",
                    "status":"draft",
                    "title":"Studio import",
                    "sourcePath":null,
                    "targetPath":"/studio-import",
                    "images":{"inline":0,"missingInline":0,"featured":"none"},
                    "state":"planned",
                    "redirect":"none"
                  }],
                  "media":[],
                  "taxonomy":{"terms":[],"assignments":[]},
                  "authors":[]
                }
                JSON, (string) $client->getResponse()->getContent());
            self::assertSame([], $pages->all());
        } finally {
            fclose($file);
        }
    }

    public function testReportUsesBoundedTitlesAndOmitsRestrictedTitles(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->multipartHeaders($client);
        $ledger = $this->createStub(WordPressImportLedger::class);
        $ledger->method('contains')->willReturn(false);
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);
        self::getContainer()->set(DoctrinePageRepository::class, new InMemoryPageRepository());
        self::getContainer()->set(DoctrineContentTypeRepository::class, new InMemoryContentTypeRepository());
        self::getContainer()->set(DoctrineSiteSettingsRepository::class, new InMemorySiteSettingsRepository());

        $file = tmpfile();
        self::assertIsResource($file);
        try {
            $items = sprintf(<<<'XML'
                <item>
                  <title>%s</title>
                  <wp:post_id>13</wp:post_id>
                  <wp:post_type>page</wp:post_type>
                  <wp:status>publish</wp:status>
                </item>
                <item>
                  <title>Protected title</title>
                  <wp:post_id>14</wp:post_id>
                  <wp:post_type>post</wp:post_type>
                  <wp:status>publish</wp:status>
                  <wp:post_password>fixture</wp:post_password>
                </item>
                <item>
                  <title>Private title</title>
                  <wp:post_id>15</wp:post_id>
                  <wp:post_type>post</wp:post_type>
                  <wp:status>private</wp:status>
                </item>
                XML, str_repeat('é', 201));
            fwrite($file, str_replace('</channel>', $items.'</channel>', self::EXPORT));
            $client->request('POST', self::ENDPOINT, ['source' => 'legacy-site', 'mode' => 'dry-run'], ['file' => $this->upload($file)], $headers);

            self::assertResponseIsSuccessful();
            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($response);
            $items = $response['items'] ?? null;
            self::assertIsArray($items);
            self::assertCount(4, $items);
            self::assertIsArray($items[1]);
            self::assertSame('invalid', $items[1]['state']);
            self::assertSame(str_repeat('é', 200), $items[1]['title']);
            self::assertIsArray($items[2]);
            self::assertArrayNotHasKey('title', $items[2]);
            self::assertIsArray($items[3]);
            self::assertArrayNotHasKey('title', $items[3]);
        } finally {
            fclose($file);
        }
    }

    public function testHistoricalLinksUseExistingDraftIdentityAndRejectUnavailableOrAmbiguousTargets(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->multipartHeaders($client);
        $pages = new InMemoryPageRepository();
        $prior = Page::draft(PageId::new(), new PageTitle('Previously imported'), new PageSlug('renamed-page'), new \DateTimeImmutable());
        $pages->add($prior);
        $missing = PageId::new()->value;
        $ledger = $this->createMock(WordPressImportLedger::class);
        $ledger->method('contains')->willReturnCallback(static fn (string $source, string $id): bool => $source === 'legacy-site' && $id === '15');
        $ledger->method('draftPermalinks')->willReturnMap([
            ['legacy-site', [
                ['externalId' => '1', 'url' => 'https://wordpress.example/old/', 'kind' => 'page', 'targetId' => $prior->id->value],
                ['externalId' => '2', 'url' => 'https://wordpress.example/gone', 'kind' => 'page', 'targetId' => $missing],
                ['externalId' => '3', 'url' => 'https://wordpress.example/duplicate', 'kind' => 'page', 'targetId' => $prior->id->value],
                ['externalId' => '4', 'url' => 'http://wordpress.example/duplicate/', 'kind' => 'page', 'targetId' => $missing],
                ['externalId' => '15', 'url' => 'https://wordpress.example/private', 'kind' => 'page', 'targetId' => $prior->id->value],
            ]],
            ['other-site', []],
        ]);
        $ledger->method('transactional')->willReturnCallback(static fn (\Closure $operation): mixed => $operation());
        $ledger->expects(self::never())->method('recordDraftPermalink');
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);
        self::getContainer()->set(DoctrinePageRepository::class, $pages);
        self::getContainer()->set(DoctrineContentTypeRepository::class, new InMemoryContentTypeRepository());
        self::getContainer()->set(DoctrineSiteSettingsRepository::class, new InMemorySiteSettingsRepository());

        $xml = <<<'XML'
            <rss xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
              <channel>
                <link>https://wordpress.example/</link><wp:wxr_version>1.2</wp:wxr_version>
                <item><title>Cross-batch links</title><wp:post_id>12</wp:post_id><wp:post_name>cross-batch</wp:post_name>
                  <wp:post_type>page</wp:post_type><wp:status>draft</wp:status>
                  <content:encoded><![CDATA[<p><a href="https://wordpress.example/old#intro">Known</a>
                    <a href="https://wordpress.example/gone">Missing</a>
                    <a href="https://wordpress.example/duplicate">Ambiguous</a>
                    <a href="https://wordpress.example/private">Private</a></p>]]></content:encoded>
                </item>
                <item><title>Private source</title><wp:post_id>15</wp:post_id><wp:post_type>page</wp:post_type>
                  <wp:status>private</wp:status><link>https://wordpress.example/private/</link>
                </item>
              </channel>
            </rss>
            XML;
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $this->createStub(WordPressImportRunHistory::class));
        foreach (['legacy-site' => 'cross-batch', 'other-site' => 'cross-batch-isolated'] as $source => $slug) {
            $file = tmpfile();
            self::assertIsResource($file);
            try {
                fwrite($file, str_replace('>cross-batch<', '>'.$slug.'<', $xml));
                $client->request('POST', self::ENDPOINT, ['source' => $source, 'mode' => 'apply'], ['file' => $this->upload($file)], $headers);
                self::assertResponseIsSuccessful();
            } finally {
                fclose($file);
            }
        }

        $bySlug = [];
        foreach ($pages->all() as $draft) {
            $bySlug[$draft->slug->value] = $draft;
        }
        $blocks = $bySlug['cross-batch']->blocks;
        self::assertCount(1, $blocks);
        $html = $blocks[0]->data['content'];
        self::assertStringContainsString('href="/renamed-page#intro"', $html);
        foreach (['gone', 'duplicate', 'private'] as $unresolved) {
            self::assertStringContainsString('href="https://wordpress.example/'.$unresolved.'"', $html);
        }
        self::assertSame($prior, $bySlug['renamed-page']);

        $blocks = $bySlug['cross-batch-isolated']->blocks;
        self::assertCount(1, $blocks);
        $html = $blocks[0]->data['content'];
        self::assertStringContainsString('href="https://wordpress.example/old#intro"', $html);
    }

    public function testConcurrentImportClaimReturnsConflict(): void
    {
        $client = $this->authenticatedClient();
        $headers = $this->multipartHeaders($client);
        $ledger = $this->createMock(WordPressImportLedger::class);
        $ledger->expects(self::once())->method('transactional')->willThrowException(new WordPressImportClaimConflict());
        $history = $this->createMock(WordPressImportRunHistory::class);
        $history->expects(self::never())->method('record');
        self::getContainer()->set(DoctrineWordPressImportRunHistory::class, $history);
        self::getContainer()->set(DoctrineWordPressImportLedger::class, $ledger);

        $file = tmpfile();
        self::assertIsResource($file);
        try {
            fwrite($file, self::EXPORT);
            $upload = $this->upload($file);
            $client->request('POST', self::ENDPOINT, ['source' => 'legacy-site', 'mode' => 'apply'], ['file' => $upload], $headers);

            self::assertResponseStatusCodeSame(409);
        } finally {
            fclose($file);
        }
    }

    /** @param list<Attachment> $additionalAttachments */
    private function useRestMediaSnapshot(array $additionalAttachments = [], string $imageUrl = self::REST_IMAGE_URL): InMemoryPageRepository
    {
        $rest = $this->createStub(WordPressStudioRestSource::class);
        $rest->method('readPublic')->willReturn([
            'items' => [[
                'id' => '12', 'type' => 'page', 'status' => 'publish', 'title' => 'Page with photo', 'slug' => 'rest-photo',
                'html' => '<p>Public material</p><img src="'.$imageUrl.'" alt="Sunrise">',
                'link' => '', 'passwordProtected' => false, 'terms' => [], 'thumbnailId' => '20', 'authorLogin' => null,
            ]],
            'attachments' => [
                ['id' => '20', 'eligible' => true, 'url' => $imageUrl, 'path' => 'photo.jpg', 'alt' => 'Sunrise'],
                ...$additionalAttachments,
            ],
            'terms' => [],
            'authors' => [],
            'skippedTypes' => [],
            'sourceUrl' => 'https://source.example.test',
        ]);
        $pages = new InMemoryPageRepository();
        self::getContainer()->set(WordPressRestReader::class, $rest);
        self::getContainer()->set(DoctrinePageRepository::class, $pages);
        self::getContainer()->set(DoctrineContentTypeRepository::class, new InMemoryContentTypeRepository());
        self::getContainer()->set(DoctrineSiteSettingsRepository::class, new InMemorySiteSettingsRepository());
        self::getContainer()->set(DoctrineMediaAssetRepository::class, new InMemoryMediaAssetRepository());

        return $pages;
    }

    /** @param \Closure(string,string):MediaAssetView $consume */
    private static function consumeRestImage(string $apiUrl, string $attachmentUrl, \Closure $consume, string $expectedUrl = self::REST_IMAGE_URL): MediaAssetView
    {
        self::assertSame(self::REST_URL, $apiUrl);
        self::assertSame($expectedUrl, $attachmentUrl);
        $file = tmpfile();
        self::assertIsResource($file);
        try {
            self::assertSame(5, fwrite($file, 'image'));
            $path = stream_get_meta_data($file)['uri'] ?? null;
            self::assertIsString($path);

            return $consume($path, 'photo.jpg');
        } finally {
            fclose($file);
        }
    }

    private function useTestImageProcessor(bool $reverted): void
    {
        $image = $this->createMock(ImageProcessor::class);
        $assetId = null;
        $image->expects(self::once())->method('process')->willReturnCallback(static function (string $file, MediaAssetId $id) use (&$assetId): ProcessedImage {
            self::assertSame('image', file_get_contents($file));
            $assetId = $id->value;
            return new ProcessedImage(480, 240, 10, [new MediaVariant(480, 240, 10, MediaVariant::urlFor($id, 480, false))]);
        });
        $image->expects($reverted ? self::once() : self::never())->method('remove')->willReturnCallback(static function (MediaAssetId $id) use (&$assetId): void {
            self::assertSame($assetId, $id->value);
        });
        self::getContainer()->set(LocalGdImageProcessor::class, $image);
    }

    /** @return array{media:array{0:array<mixed>},items:array{0:array<mixed>},counts:array<mixed>} */
    private function decodeRestMediaReport(KernelBrowser $client): array
    {
        $report = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertIsArray($report['media']);
        self::assertIsArray($report['media'][0]);
        self::assertIsArray($report['items']);
        self::assertIsArray($report['items'][0]);
        self::assertIsArray($report['counts']);

        return ['media' => [$report['media'][0]], 'items' => [$report['items'][0]], 'counts' => $report['counts']];
    }

    /** @param resource $file */
    private function upload($file): UploadedFile
    {
        $path = stream_get_meta_data($file)['uri'] ?? null;
        self::assertIsString($path);

        return new UploadedFile($path, 'wordpress.xml', test: true);
    }

    /** @param list<string> $roles */
    private function authenticatedClient(array $roles = ['ROLE_OWNER', 'ROLE_EDITOR']): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $user = new SecurityUser('11111111-1111-4111-8111-111111111111', 'studio@example.test', 'unused-test-hash', $roles);
        $provider = $this->createStub(UserProviderInterface::class);
        $provider->method('refreshUser')->willReturn($user);
        $provider->method('supportsClass')->willReturnCallback(static fn (string $class): bool => $class === SecurityUser::class);
        self::getContainer()->set(SecurityUserProvider::class, $provider);
        $client->loginUser($user);

        return $client;
    }

    /** @return array{CONTENT_TYPE:string,HTTP_X_CSRF_TOKEN:string} */
    private function multipartHeaders(KernelBrowser $client): array
    {
        return ['CONTENT_TYPE' => 'multipart/form-data; boundary=wxr', 'HTTP_X_CSRF_TOKEN' => $this->csrfToken($client)];
    }

    /** @return array{CONTENT_TYPE:string,HTTP_X_CSRF_TOKEN:string} */
    private function restHeaders(KernelBrowser $client): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->csrfToken($client)];
    }

    private function csrfToken(KernelBrowser $client): string
    {
        $client->request('GET', '/api/v1/auth/csrf');
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($response);
        self::assertIsString($response['token']);

        return $response['token'];
    }
}
