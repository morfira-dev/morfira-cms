<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\Import\SegmentWordPressImages;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use PHPUnit\Framework\TestCase;

final class SegmentWordPressImagesTest extends TestCase
{
    private const string ASSET = '11111111-1111-4111-8111-111111111111';

    public function testPreservesOrderFormattingAndGutenbergCaptions(): void
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $result = $segment(
            '<p>До <strong>A<img src="/uploads/image.png" alt="Inline alt"> B</strong></p>'
                .'<figure class="wp-block-image"><img class="wp-image-17" src="/uploads/image-150x150.png">'
                .'<figcaption>Подпись <em>изображения</em></figcaption></figure>',
            static function (string $source, ?string $wordpressId): ?array {
                if ($source !== '/uploads/image.png' && $wordpressId !== '17') {
                    return null;
                }

                return ['assetId' => self::ASSET, 'alt' => 'Media alt'];
            },
        );

        self::assertSame(0, $result['missingImages']);
        self::assertSame([
            ['type' => 'richText', 'html' => '<p>До <strong>A</strong></p>'],
            ['type' => 'image', 'assetId' => self::ASSET, 'alt' => 'Inline alt'],
            ['type' => 'richText', 'html' => '<p><strong> B</strong></p>'],
            ['type' => 'image', 'assetId' => self::ASSET, 'alt' => 'Media alt'],
            ['type' => 'richText', 'html' => '<p>Подпись <em>изображения</em></p>'],
        ], $result['segments']);
    }

    public function testIgnoresActiveSubtreesAndReportsUnmatchedImagesWithoutLosingCaptions(): void
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $sources = [];
        $result = $segment(
            '<template><img src="/concealed.png"></template><script>leak()</script>'
                .'<figure><img src="https://foreign.example/missing.png" onerror="leak()"><figcaption>Visible caption</figcaption></figure>'
                .'<p><a href="javascript:alert(1)">Safe text</a></p>',
            static function (string $source, ?string $wordpressId) use (&$sources): ?array {
                $sources[] = [$source, $wordpressId];

                return null;
            },
        );

        self::assertSame([['https://foreign.example/missing.png', null]], $sources);
        self::assertSame(1, $result['missingImages']);
        self::assertCount(1, $result['segments']);
        $richText = $result['segments'][0];
        self::assertSame('richText', $richText['type']);
        self::assertStringContainsString('Visible caption', $richText['html']);
        self::assertStringContainsString('Safe text', $richText['html']);
        self::assertStringNotContainsString('javascript:', $richText['html']);
        self::assertStringNotContainsString('leak()', $richText['html']);
    }

    public function testHandlesLazySourcesAndUsesRequiredFallbackAlt(): void
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $result = $segment(
            '<img src="/placeholder.png" data-src="/uploads/real.png">',
            static fn (string $source, ?string $wordpressId): ?array => $source === '/uploads/real.png'
                ? ['assetId' => self::ASSET, 'alt' => '']
                : null,
        );

        self::assertSame([
            'segments' => [['type' => 'image', 'assetId' => self::ASSET, 'alt' => 'Image']],
            'missingImages' => 0,
        ], $result);
    }
}
