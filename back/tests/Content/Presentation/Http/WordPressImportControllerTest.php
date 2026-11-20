<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Presentation\Http;

use Cms\Content\Application\Import\WordPressImportClaimConflict;
use Cms\Content\Application\Import\WordPressImportLedger;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageSlug;
use Cms\Content\Domain\PageTitle;
use Cms\Content\Infrastructure\Import\DoctrineWordPressImportLedger;
use Cms\Content\Infrastructure\Persistence\DoctrineEntryRepository;
use Cms\Content\Infrastructure\Persistence\DoctrinePageRepository;
use Cms\Content\Infrastructure\Persistence\DoctrineRelationTargetLookup;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Cms\Identity\Infrastructure\Security\SecurityUserProvider;
use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaVariant;
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

final class WordPressImportControllerTest extends WebTestCase
{
    private const string ENDPOINT = '/api/v1/admin/imports/wordpress';
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
        $ledger->method('transactional')->willReturnCallback(static fn (\Closure $operation): mixed => $operation());
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
            self::assertSame(['parent', 'child'], array_column(array_values($taxonomies->termItems), 'slug'));
            $postId = $entries->all()[1]->id->value;
            self::assertSame(['child'], array_column($taxonomies->entryTerms($postId), 'slug'));
            self::assertSame($assetId->value, $entries->all()[1]->values['wordpressFeaturedImage']);
            self::assertSame($assetId->value, $entries->all()[1]->values['wordpressInlineImage1']);

            $client->request('POST', self::ENDPOINT, $fields, ['file' => $this->upload($file)], $headers);
            self::assertResponseIsSuccessful();
            $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($response);
            self::assertSame(['already_imported' => 2], $response['counts']);
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
        $client->request('GET', '/api/v1/auth/csrf');
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($response);
        self::assertIsString($response['token']);

        return ['CONTENT_TYPE' => 'multipart/form-data; boundary=wxr', 'HTTP_X_CSRF_TOKEN' => $response['token']];
    }
}
