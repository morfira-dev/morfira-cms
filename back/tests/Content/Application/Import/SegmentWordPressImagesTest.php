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

    public function testNormalizesGutenbergTextWithoutInlineImages(): void
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $result = $segment(
            '<h1 class="wp-block-heading">Imported heading</h1>'
                .'<template><h1 class="wp-block-heading">Discarded heading</h1></template>'
                .'<p class="wp-block-paragraph">Imported paragraph</p>',
            static fn (string $source, ?string $wordpressId): never => throw new \LogicException('Text blocks do not resolve images.'),
        );

        self::assertSame([
            'segments' => [['type' => 'richText', 'html' => '<h2>Imported heading</h2><p>Imported paragraph</p>']],
            'missingImages' => 0,
        ], $result);
    }

    public function testKeepsGalleryAndMediaWithTextInTheirSourceOrder(): void
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $result = $segment(
            '<div class="wp-block-group"><p>Before</p>'
                .'<figure class="wp-block-gallery"><figure class="wp-block-image"><img src="/gallery.png" alt="Gallery">'
                .'<figcaption>Gallery caption</figcaption></figure></figure>'
                .'<div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="/media.png" alt="Media"></figure>'
                .'<div class="wp-block-media-text__content"><p>Alongside media</p></div></div></div>',
            static fn (string $source, ?string $wordpressId): array => ['assetId' => self::ASSET, 'alt' => 'Attachment'],
            nativeCaptions: true,
        );

        self::assertSame([
            'segments' => [
                ['type' => 'richText', 'html' => '<p>Before</p>'],
                ['type' => 'image', 'assetId' => self::ASSET, 'alt' => 'Gallery', 'caption' => 'Gallery caption'],
                ['type' => 'image', 'assetId' => self::ASSET, 'alt' => 'Media'],
                ['type' => 'richText', 'html' => '<p>Alongside media</p>'],
            ],
            'missingImages' => 0,
        ], $result);
    }

    public function testResolvesClassicGalleryIdsWithoutImageMarkupAndLeavesCodeLiteral(): void
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $requested = [];
        $result = $segment(
            '<p>Before</p>[gallery ids="21,22"]<pre><code>[gallery ids="23"]</code></pre>',
            static function (string $source, ?string $wordpressId) use (&$requested): ?array {
                $requested[] = [$source, $wordpressId];

                return $wordpressId === '21' ? ['assetId' => self::ASSET, 'alt' => 'Gallery attachment'] : null;
            },
        );

        self::assertSame([['', '21'], ['', '22']], $requested);
        self::assertSame(1, $result['missingImages']);
        self::assertSame(['richText', 'image', 'richText'], array_column($result['segments'], 'type'));
        self::assertSame('Gallery attachment', $result['segments'][1]['alt'] ?? null);
        self::assertStringContainsString('[gallery ids="23"]', html_entity_decode($result['segments'][2]['html'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testImportsPlainCaptionsForResolvedPageFigures(): void
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $result = $segment(
            '<p>Before</p><figure class="wp-block-image"><img src="/image.png">'
                .'<figcaption> &nbsp;Подпись &amp; &lt;literal&gt; </figcaption></figure>'
                .'<figure><a href="https://example.com/image"><img src="/linked.png"></a>'
                .'<figcaption>'.str_repeat('я', 500).'</figcaption></figure><p>After</p>',
            static fn (string $source, ?string $wordpressId): array => ['assetId' => self::ASSET, 'alt' => 'Media alt'],
            nativeCaptions: true,
        );

        self::assertSame(0, $result['missingImages']);
        self::assertSame([
            ['type' => 'richText', 'html' => '<p>Before</p>'],
            ['type' => 'image', 'assetId' => self::ASSET, 'alt' => 'Media alt', 'caption' => 'Подпись & <literal>'],
            ['type' => 'image', 'assetId' => self::ASSET, 'alt' => 'Media alt', 'caption' => str_repeat('я', 500)],
            ['type' => 'richText', 'html' => '<p>After</p>'],
        ], $result['segments']);
    }

    public function testRetainsCaptionsInTextForPostsAndIneligiblePageFigures(): void
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $resolve = static fn (string $source, ?string $wordpressId): ?array => $source === '/image.png'
            ? ['assetId' => self::ASSET, 'alt' => 'Media alt']
            : null;
        $simple = '<figure><img src="/image.png"><figcaption>Plain caption</figcaption></figure>';
        $oversized = str_repeat('я', 501);

        self::assertSame([
            ['type' => 'image', 'assetId' => self::ASSET, 'alt' => 'Media alt'],
            ['type' => 'richText', 'html' => '<p>Plain caption</p>'],
        ], $segment($simple, $resolve)['segments']);

        $result = $segment(
            '<figure><img src="/missing.png"><figcaption>Unresolved &amp; visible</figcaption></figure>'
                .'<figure><img src="/image.png"><figcaption>Styled <em>caption</em><script>hidden</script></figcaption></figure>'
                .'<figure><img src="/image.png"><figcaption>'.$oversized.'</figcaption></figure>'
                .'<figure><img src="/image.png"><img src="/image.png"><figcaption>Gallery caption</figcaption></figure>',
            $resolve,
            nativeCaptions: true,
        );

        self::assertSame(1, $result['missingImages']);
        self::assertSame([], array_column($result['segments'], 'caption'));
        $html = implode('', array_column($result['segments'], 'html'));
        self::assertStringContainsString('<p>Unresolved &amp; visible</p>', $html);
        self::assertStringContainsString('<p>Styled <em>caption</em></p>', $html);
        self::assertStringContainsString($oversized, $html);
        self::assertStringContainsString('<p>Gallery caption</p>', $html);
        self::assertStringNotContainsString('hidden', $html);
    }
}
