<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Infrastructure\Import\WordPressRestMapper;
use PHPUnit\Framework\TestCase;

final class WordPressRestMapperTest extends TestCase
{
    public function testNormalizesPublicPostsAndPreservesResourceIdentity(): void
    {
        $export = new WordPressRestMapper()('https://wordpress.example/blog', [
            'posts' => [[
                'id' => '18446744073709551615',
                'status' => 'publish',
                'slug' => 'welcome',
                'title' => ['rendered' => '<b>News &amp; notes</b>'],
                'content' => ['rendered' => '<p>Hello</p><img class="wp-image-57">', 'protected' => false],
                'link' => 'https://wordpress.example/blog/2026/welcome/',
                'author' => 23,
                'featured_media' => 57,
                'categories' => [10, 10],
                'tags' => [12, 900],
            ]],
            'media' => [[
                'id' => 57,
                'source_url' => 'https://assets.example/2026/image.png',
                'alt_text' => 'Image alt',
                'media_details' => ['file' => '2026/image.png'],
                'media_type' => 'image',
                'mime_type' => 'image/png',
                'status' => 'inherit',
                'post' => '18446744073709551615',
            ]],
            'categories' => [
                ['id' => 10, 'slug' => 'child', 'name' => 'Child', 'parent' => 11],
                ['id' => 11, 'slug' => 'parent', 'name' => 'Parent', 'parent' => 0],
            ],
            'tags' => [['id' => 12, 'slug' => 'migration', 'name' => 'Migration']],
            'users' => [['id' => 23, 'slug' => 'editor', 'name' => 'Test &amp; Writer', 'email' => 'ignored@example.test']],
        ]);
        self::assertSame('https://wordpress.example/blog', $export['sourceUrl']);
        self::assertSame([
            'id' => '18446744073709551615',
            'type' => 'post',
            'status' => 'publish',
            'title' => 'News & notes',
            'slug' => 'welcome',
            'html' => '<p>Hello</p><img class="wp-image-57">',
            'link' => 'https://wordpress.example/blog/2026/welcome/',
            'passwordProtected' => false,
            'terms' => [
                ['taxonomy' => 'category', 'slug' => 'child'],
                ['taxonomy' => 'post_tag', 'slug' => 'migration'],
                ['taxonomy' => 'post_tag', 'slug' => '#missing-900'],
            ],
            'thumbnailId' => '57',
            'authorLogin' => 'rest-author-23',
        ], $export['items'][0]);
        self::assertSame([
            'id' => '57',
            'path' => '2026/image.png',
            'url' => 'https://assets.example/2026/image.png',
            'alt' => 'Image alt',
            'eligible' => true,
        ], $export['attachments'][0]);
        self::assertSame([
            ['taxonomy' => 'category', 'slug' => 'child', 'name' => 'Child', 'parent' => 'parent'],
            ['taxonomy' => 'category', 'slug' => 'parent', 'name' => 'Parent', 'parent' => ''],
            ['taxonomy' => 'post_tag', 'slug' => 'migration', 'name' => 'Migration', 'parent' => ''],
        ], $export['terms']);
        self::assertSame([['login' => 'rest-author-23', 'name' => 'Test & Writer']], $export['authors']);
        self::assertSame('rest-author-23', (new WordPressRestMapper()('https://wordpress.example/blog', [
            'users' => [['id' => 23, 'slug' => 'changed', 'name' => 'Updated name']],
        ]))['authors'][0]['login']);
    }

    public function testWithholdsProtectedContentAndMedia(): void
    {
        $export = new WordPressRestMapper()('https://wordpress.example', [
            'pages' => [[
                'id' => 5,
                'status' => 'publish',
                'content' => ['rendered' => 'Protected', 'protected' => true],
                'title' => ['rendered' => 'Protected title'],
                'link' => 'https://wordpress.example/not-public/',
                'author' => 9,
                'featured_media' => 6,
            ], [
                'id' => 10,
                'status' => 'draft',
                'content' => ['rendered' => 'Draft', 'protected' => false],
            ]],
            'media' => [
                ['id' => 6, 'status' => 'inherit', 'media_type' => 'image', 'mime_type' => 'image/jpeg', 'post' => 5],
                ['id' => 7, 'status' => 'inherit', 'media_type' => 'image', 'mime_type' => 'image/jpeg', 'post' => 0],
                ['id' => 8, 'status' => 'inherit', 'media_type' => 'image', 'mime_type' => 'image/svg+xml', 'post' => 0],
                ['id' => 9, 'status' => 'inherit', 'media_type' => 'image', 'mime_type' => 'image/jpeg', 'post' => 99],
            ],
        ]);

        self::assertSame(['publish', 'private'], array_column($export['items'], 'status'));
        self::assertSame('page', $export['items'][0]['type']);
        self::assertTrue($export['items'][0]['passwordProtected']);
        self::assertSame(['', ''], array_column($export['items'], 'html'));
        self::assertSame(['', ''], array_column($export['items'], 'link'));
        self::assertNull($export['items'][0]['authorLogin']);
        self::assertNull($export['items'][0]['thumbnailId']);
        self::assertSame([false, true, false], array_column($export['attachments'], 'eligible'));
        self::assertSame(['unsupported_media' => 1], $export['skippedTypes']);
    }

    public function testRejectsOverlappingResourceIds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('overlapping content identifier');

        new WordPressRestMapper()('https://wordpress.example', ['posts' => [['id' => 1]], 'media' => [['id' => '1']]]);
    }

    public function testRejectsDuplicateIdsFromPagination(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid or duplicate identifier');

        new WordPressRestMapper()('https://wordpress.example', ['posts' => [['id' => 1], ['id' => '1']]]);
    }
}
