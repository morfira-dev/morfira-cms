<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\Import\NormalizeWordPressLinkBlock;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use DOMDocument;
use PHPUnit\Framework\TestCase;

final class NormalizeWordPressLinkBlockTest extends TestCase
{
    public function testRecoversStaticLinksFromExplicitLabelsAndImageAlternatives(): void
    {
        $html = $this->normalize(
            '<ul class="wp-block-social-links has-extra-classes"><li>'
                .'<a href="https://example.org/news" aria-label="News &amp; updates" title="Fallback"><svg><title>Hidden icon</title></svg></a>'
                .'</li></ul>'
                .'<ul class="wp-block-rss"><li><a href="/feed" title="Feed title"><span>&nbsp;</span></a></li></ul>'
                .'<ul class="wp-block-page-list"><li><a href="/library"><span><img src="/icon.png" alt="Library"></span></a></li></ul>'
                .'<a class="wp-block-read-more" href="/article" aria-label="Read the article"></a>',
        );

        self::assertStringContainsString('<a href="https://example.org/news" title="Fallback" rel="noopener noreferrer">News &amp; updates</a>', $html);
        self::assertStringContainsString('<a href="/feed" title="Feed title" rel="noopener noreferrer">Feed title</a>', $html);
        self::assertStringContainsString('<a href="/library" rel="noopener noreferrer">Library</a>', $html);
        self::assertStringContainsString('<a href="/article" rel="noopener noreferrer">Read the article</a>', $html);
        self::assertStringNotContainsString('Hidden icon', $html);
        self::assertStringNotContainsString('/icon.png', $html);
    }

    public function testPreservesReadableNavigationInOrderAndDropsItsToggle(): void
    {
        $html = $this->normalize(
            '<nav class="wp-block-navigation"><ul><li><a href="/first" aria-label="Unused"><strong>First</strong></a>'
                .'<button class="wp-block-navigation__submenu-icon" data-href="/never">Toggle submenu</button>'
                .'<ul><li><a href="/second" aria-label="Second"><svg></svg></a></li></ul>'
                .'</li></ul></nav>',
        );

        self::assertStringContainsString('<ul><li><a href="/first" rel="noopener noreferrer"><strong>First</strong></a>'
            .'<ul><li><a href="/second" rel="noopener noreferrer">Second</a></li></ul></li></ul>', $html);
        self::assertStringNotContainsString('Toggle submenu', $html);
        self::assertStringNotContainsString('/never', $html);
        self::assertStringNotContainsString('Unused', $html);
    }

    public function testLeavesOtherLinksUnchangedAndKeepsSanitizerInControlOfDestinations(): void
    {
        $html = $this->normalize(
            '<div class="wp-block-social-links-custom"><a href="/other" aria-label="Outside label"><svg></svg></a></div>'
                .'<nav class="wp-block-navigation"><template><a href="/hidden" aria-label="Inert label"><svg></svg></a></template>'
                .'<a href="javascript:alert(1)" aria-label="Unsafe link"><svg></svg></a>'
                .'<a href="/empty"><svg><title>Icon is not a label</title></svg><script>Hidden script</script></a></nav>',
        );

        self::assertStringNotContainsString('Outside label', $html);
        self::assertStringNotContainsString('Inert label', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('Icon is not a label', $html);
        self::assertStringNotContainsString('Hidden script', $html);
    }

    private function normalize(string $html): string
    {
        $document = new DOMDocument();
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach (array_reverse(iterator_to_array($document->getElementsByTagName('*'), false)) as $node) {
            NormalizeWordPressLinkBlock::normalize($node);
        }
        $body = $document->getElementsByTagName('body')->item(0);
        self::assertNotNull($body);
        $source = $document->saveHTML($body);
        self::assertIsString($source);

        return (new SymfonyRichTextSanitizer())->sanitize($source);
    }
}
