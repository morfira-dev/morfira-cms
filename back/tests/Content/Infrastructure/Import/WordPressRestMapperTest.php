<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Infrastructure\Import\WordPressRestMapper;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testEditMediaIsPrivateUnlessTheAnonymousCollectionConfirmsPublicVisibility(): void
    {
        $base = ['status' => 'inherit', 'media_type' => 'image', 'mime_type' => 'image/jpeg', 'post' => 0];
        $source = 'https://wordpress.example';
        $authenticated = [
            ['id' => 1, 'alt_text' => 'Edit-only metadata'] + $base,
            ['id' => 2, 'status' => 'private'] + $base,
            ['id' => 3, 'post' => 50] + $base,
            ['id' => 4, 'post' => 999] + $base,
            ['id' => 5] + $base,
            ['id' => 6, 'status' => 'trash'] + $base,
        ];
        $export = new WordPressRestMapper()($source, [
            'posts' => [['id' => 50, 'status' => 'private', 'content' => ['raw' => '', 'protected' => false]]],
            'media' => [['id' => 1, 'alt_text' => 'Public metadata'] + $base, ['id' => 3, 'post' => 50] + $base],
            'authenticatedMedia' => $authenticated,
        ], true);

        self::assertSame('Public metadata', $export['attachments'][0]['alt']);
        self::assertArrayNotHasKey('isPrivate', $export['attachments'][0]);
        self::assertSame([true, true, true, true, true, false], array_column($export['attachments'], 'eligible'));
        foreach (array_slice($export['attachments'], 1) as $attachment) {
            self::assertTrue($attachment['isPrivate'] ?? false);
        }
    }

    #[DataProvider('restrictedStatuses')]
    public function testAuthenticatedReaderUsesTheEditContextBodyAndConcealsTheSourceLink(string $status, bool $protected): void
    {
        $collections = [
            'posts' => [[
                'id' => 51,
                'status' => $status,
                'slug' => 'closed-source-slug',
                'title' => ['raw' => 'Original &amp; title', 'rendered' => 'Filtered title'],
                'content' => ['raw' => '<!-- wp:paragraph --><p>Editor body.</p><!-- /wp:paragraph -->', 'rendered' => 'Filtered body', 'protected' => $protected],
                'link' => 'https://wordpress.example/?p=51',
                'password' => 'ignored-post-protection-value',
                'author' => 23,
                'categories' => [10],
            ]],
            'categories' => [['id' => 10, 'slug' => 'migration', 'name' => 'Migration']],
            'media' => [
                ['id' => 9, 'status' => 'inherit', 'media_type' => 'image', 'mime_type' => 'image/jpeg', 'post' => 51],
                ['id' => 10, 'status' => 'inherit', 'media_type' => 'image', 'mime_type' => 'image/jpeg', 'post' => 0],
            ],
        ];

        $mapper = new WordPressRestMapper();
        $export = $mapper('https://wordpress.example', $collections, true);
        self::assertSame([
            'id' => '51',
            'type' => 'post',
            'status' => $status,
            'title' => 'Original & title',
            'slug' => 'closed-source-slug',
            'html' => '<!-- wp:paragraph --><p>Editor body.</p><!-- /wp:paragraph -->',
            'link' => '',
            'passwordProtected' => $protected,
            'terms' => [['taxonomy' => 'category', 'slug' => 'migration']],
            'thumbnailId' => null,
            'authorLogin' => 'rest-author-23',
            'restrictedSource' => true,
        ], $export['items'][0]);
        self::assertSame([false, true], array_column($export['attachments'], 'eligible'));
        self::assertStringNotContainsString('ignored-post-protection-value', json_encode($export, JSON_THROW_ON_ERROR));

        $public = $mapper('https://wordpress.example', $collections);
        self::assertSame('', $public['items'][0]['html']);
        self::assertArrayNotHasKey('restrictedSource', $public['items'][0]);
    }

    /** @return iterable<string,array{string,bool}> */
    public static function restrictedStatuses(): iterable
    {
        yield 'private' => ['private', false];
        yield 'draft' => ['draft', false];
        yield 'pending' => ['pending', false];
        yield 'scheduled' => ['future', false];
        yield 'password-protected published' => ['publish', true];
    }

    public function testIncompleteEditContextDoesNotFallBackToRenderedRestrictedContent(): void
    {
        $records = [
            ['id' => 51, 'status' => 'draft', 'content' => ['rendered' => 'Filtered draft', 'protected' => false]],
            ['id' => 52, 'status' => 'publish', 'content' => ['raw' => 'Missing protection flag']],
            ['id' => 53, 'status' => 'publish', 'content' => ['raw' => ['Not a body'], 'protected' => true]],
            ['id' => 54, 'status' => 'trash', 'content' => ['raw' => 'Trashed content', 'protected' => false]],
        ];
        $export = new WordPressRestMapper()('https://wordpress.example', ['posts' => $records], true);

        self::assertSame(['private', 'publish', 'publish', 'private'], array_column($export['items'], 'status'));
        self::assertSame(['', '', '', ''], array_column($export['items'], 'html'));
        self::assertSame([false, true, true, false], array_column($export['items'], 'passwordProtected'));
        self::assertSame([], array_column($export['items'], 'restrictedSource'));
    }

    public function testRejectsDuplicateIdsFromPagination(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid or duplicate identifier');

        new WordPressRestMapper()('https://wordpress.example', ['posts' => [['id' => 1], ['id' => '1']]]);
    }
}
