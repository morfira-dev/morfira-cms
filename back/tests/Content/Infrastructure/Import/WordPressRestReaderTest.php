<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressRestCredentials;
use Cms\Content\Infrastructure\Import\WordPressRestJsonTransport;
use Cms\Content\Infrastructure\Import\WordPressRestMapper;
use Cms\Content\Infrastructure\Import\WordPressRestReader;
use Cms\Content\Infrastructure\Import\WordPressRestUnsafeAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WordPressRestReaderTest extends TestCase
{
    private const string API = 'https://wordpress.example/blog/wp-json/wp/v2';

    public function testReadsAnonymousAndAuthenticatedMediaSeparately(): void
    {
        $seen = [];
        $credentials = new WordPressRestCredentials('fixture-editor', 'fixtureApplicationPassword');
        $transport = $this->createStub(WordPressRestJsonTransport::class);
        $transport->method('get')->willReturnCallback(static function (string $base, string $resource, array $query, ?WordPressRestCredentials $auth) use (&$seen, $credentials): array {
            self::assertSame(self::API, $base);
            $seen[] = [$resource, $query['context'], $query['status'] ?? null, $auth === $credentials];
            return ['body' => [], 'totalPages' => 0];
        });

        new WordPressRestReader($transport, new WordPressRestMapper())->read(self::API, $credentials);

        self::assertSame([
            ['posts', 'edit', 'publish,private,draft,pending,future', true],
            ['pages', 'edit', 'publish,private,draft,pending,future', true],
            ['media', 'view', null, false],
            ['categories', 'view', null, false],
            ['tags', 'view', null, false],
            ['users', 'view', null, false],
            ['media', 'edit', 'inherit,private', true],
        ], $seen);
    }

    public function testFetchesNumberedPagesAndNormalizesTheWholeBatch(): void
    {
        $seen = [];
        $transport = $this->createStub(WordPressRestJsonTransport::class);
        $transport->method('get')->willReturnCallback(static function (string $base, string $resource, array $query) use (&$seen): array {
            self::assertSame(self::API, $base);
            self::assertSame(50, $query['per_page']);
            self::assertSame('view', $query['context']);
            $seen[] = [$resource, $query['page']];
            if ($resource !== 'posts') {
                return ['body' => [], 'totalPages' => 0];
            }
            self::assertSame('publish', $query['status']);
            self::assertSame('id', $query['orderby']);
            $ids = $query['page'] === 1 ? range(1, 50) : [51];
            $body = array_map(static fn (int $id): array => [
                'id' => $id,
                'status' => 'publish',
                'slug' => 'post-'.$id,
                'title' => ['rendered' => 'Title '.$id],
                'content' => ['rendered' => '<p>Body</p>', 'protected' => false],
            ], $ids);

            return ['body' => $body, 'totalPages' => 2];
        });

        $report = new WordPressRestReader($transport, new WordPressRestMapper())->read(self::API.'/');

        self::assertSame([['posts', 1], ['posts', 2], ['pages', 1], ['media', 1], ['categories', 1], ['tags', 1], ['users', 1]], $seen);
        self::assertSame('https://wordpress.example/blog', $report['sourceUrl']);
        self::assertCount(51, $report['items']);
        self::assertSame('51', $report['items'][50]['id']);
    }

    public function testStudioReadsAnonymouslyWithOneDeadlineAndRemainingBodyBudget(): void
    {
        $seen = [];
        $started = (int) hrtime(true);
        $transport = $this->createMock(WordPressRestJsonTransport::class);
        $transport->expects(self::exactly(6))->method('get')->willReturnCallback(static function (
            string $base,
            string $resource,
            array $query,
            ?WordPressRestCredentials $auth,
            ?int $deadline,
            ?int $bodyLimit,
        ) use (&$seen, $started): array {
            self::assertSame(self::API, $base);
            self::assertNull($auth);
            self::assertSame('view', $query['context']);
            self::assertGreaterThanOrEqual($started + 12_000_000_000, $deadline);
            self::assertLessThanOrEqual((int) hrtime(true) + 12_000_000_000, $deadline);
            self::assertSame($seen[0][1] ?? $deadline, $deadline);
            self::assertSame(4 * 1024 * 1024 - count($seen) * 100, $bodyLimit);
            $seen[] = [$resource, $deadline];

            return ['body' => [], 'totalPages' => 0, 'bodyBytes' => 100];
        });

        $export = new WordPressRestReader($transport, new WordPressRestMapper())->readPublic(self::API.'/');

        self::assertSame(['posts', 'pages', 'media', 'categories', 'tags', 'users'], array_column($seen, 0));
        self::assertSame('https://wordpress.example/blog', $export['sourceUrl']);
    }

    public function testStudioRejectsAnIncompleteBatchAtItsRequestLimit(): void
    {
        $transport = $this->createMock(WordPressRestJsonTransport::class);
        $transport->expects(self::exactly(12))->method('get')->willReturnCallback(static function (string $base, string $resource, array $query): array {
            self::assertSame('posts', $resource);
            $page = $query['page'];
            self::assertIsInt($page);
            $end = $page * 50;

            return ['body' => array_map(static fn (int $id): array => ['id' => $id], range($end - 49, $end)), 'totalPages' => 12];
        });

        $this->expectExceptionMessage('WordPress REST request limit exceeded.');
        new WordPressRestReader($transport, new WordPressRestMapper())->readPublic(self::API);
    }

    public function testStudioCountsWireBytesAndStopsBeforeTheNextRequest(): void
    {
        $transport = $this->createMock(WordPressRestJsonTransport::class);
        $transport->expects(self::exactly(2))->method('get')->willReturn(['body' => [], 'totalPages' => 0, 'bodyBytes' => 2 * 1024 * 1024]);

        $this->expectExceptionMessage('WordPress REST snapshot exceeds the import limits.');
        new WordPressRestReader($transport, new WordPressRestMapper())->readPublic(self::API);
    }

    public function testStudioClassifiesKnownNonpublicAddressesAsInvalidInput(): void
    {
        $transport = $this->createStub(WordPressRestJsonTransport::class);
        $transport->method('get')->willThrowException(new WordPressRestUnsafeAddress('The WordPress host must resolve only to public addresses.'));

        $this->expectException(\InvalidArgumentException::class);
        new WordPressRestReader($transport, new WordPressRestMapper())->readPublic(self::API);
    }

    public function testStudioClassifiesMalformedRemoteMetadataAsReadFailure(): void
    {
        $transport = $this->createStub(WordPressRestJsonTransport::class);
        $transport->method('get')->willReturnCallback(static fn (string $base, string $resource): array => in_array($resource, ['posts', 'pages'], true)
            ? ['body' => [['id' => 1]], 'totalPages' => 1]
            : ['body' => [], 'totalPages' => 0]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WordPress REST returned invalid import metadata.');
        new WordPressRestReader($transport, new WordPressRestMapper())->readPublic(self::API);
    }

    /** @param array{body:list<array{id:int}>,totalPages:int} $second */
    #[DataProvider('invalidSecondPages')]
    public function testRejectsPartialOrInconsistentCollections(array $second, string $message): void
    {
        $transport = $this->createMock(WordPressRestJsonTransport::class);
        $transport->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            ['body' => array_map(static fn (int $id): array => ['id' => $id], range(1, 50)), 'totalPages' => 2],
            $second,
        );

        $this->expectExceptionMessage($message);
        new WordPressRestReader($transport, new WordPressRestMapper())->read(self::API);
    }

    /** @return iterable<string,array{array{body:list<array{id:int}>,totalPages:int},string}> */
    public static function invalidSecondPages(): iterable
    {
        yield 'duplicate ID' => [['body' => [['id' => 50]], 'totalPages' => 2], 'duplicate collection ID'];
        yield 'page count changed' => [['body' => [['id' => 51]], 'totalPages' => 3], 'pagination changed'];
        yield 'truncated result' => [['body' => [], 'totalPages' => 2], 'inconsistent pagination'];
    }
}
