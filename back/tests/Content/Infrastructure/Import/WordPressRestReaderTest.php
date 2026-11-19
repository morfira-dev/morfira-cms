<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressRestCredentials;
use Cms\Content\Infrastructure\Import\WordPressRestJsonTransport;
use Cms\Content\Infrastructure\Import\WordPressRestMapper;
use Cms\Content\Infrastructure\Import\WordPressRestReader;
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
