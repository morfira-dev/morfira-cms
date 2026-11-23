<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\Import\NormalizeWordPressTextBlock;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;

final class NormalizeWordPressTextBlockTest extends TestCase
{
    public function testHeadingAndButtonPreserveEditableMarkupAndSanitizeLinks(): void
    {
        $html = self::normalize(
            '<h1 class="wp-block-heading" id="article">An <em>article</em></h1>'
            .'<h5 class="wp-block-heading">Section</h5><h6 class="wp-block-heading">Subsection</h6>'
            .'<div class="wp-block-button"><a class="wp-block-button__link" href="/article">Read the article</a></div>'
            .'<div class="wp-block-button"><a href="javascript:bad()" onclick="bad()">Unsafe target</a></div>',
        );

        self::assertStringContainsString('<h2 id="article">An <em>article</em></h2><h4>Section</h4><h4>Subsection</h4>', $html);
        self::assertStringContainsString('<p><a href="/article" rel="noopener noreferrer">Read the article</a></p>', $html);
        self::assertStringContainsString('Unsafe target</a></p>', $html);
        self::assertStringNotContainsString('bad()', $html);
    }

    public function testQuotesDetailsAndVerseKeepAttributionAndLineBreaks(): void
    {
        $html = self::normalize(
            '<blockquote class="wp-block-quote"><p>Short quote</p><cite>Writer</cite></blockquote>'
            .'<figure class="wp-block-pullquote"><blockquote><p>Another quote</p><cite>Author</cite></blockquote></figure>'
            .'<details class="wp-block-details"><summary>Read more</summary><p>Details <strong>body</strong></p></details>'
            .'<pre class="wp-block-verse">First'."\n".'<em>Second'."\n".'Third</em><script>hidden'."\n".'secret</script></pre>',
        );

        self::assertStringContainsString('<blockquote><p>Short quote</p><p><em>Writer</em></p></blockquote>', $html);
        self::assertStringContainsString('<blockquote><p>Another quote</p><p><em>Author</em></p></blockquote>', $html);
        self::assertStringContainsString('<p><strong>Read more</strong></p><p>Details <strong>body</strong></p>', $html);
        self::assertStringContainsString('<p>First<br /><em>Second<br />Third</em></p>', $html);
        self::assertStringNotContainsString('secret', $html);
    }

    public function testTableKeepsCaptionAndRowsWithoutExposingDiscardedContent(): void
    {
        $html = self::normalize(
            '<figure class="wp-block-table"><table><caption>Numbers</caption>'
            .'<thead><tr><th>Column A</th><th>Column B</th></tr></thead>'
            .'<tbody><tr><td><strong>Value</strong></td><td><script>hidden</script>Two</td></tr></tbody>'
            .'</table><figcaption>Table notes</figcaption></figure>',
        );

        self::assertStringContainsString('<p>Numbers</p><ul><li>Column A | Column B</li><li><strong>Value</strong> | Two</li></ul><figcaption>Table notes</figcaption>', $html);
        self::assertStringNotContainsString('hidden', $html);
    }

    public function testUnrecognizedOrMalformedBlocksUseExistingFallback(): void
    {
        $input = '<h1 class="not-wp-block-heading">Unrecognized heading</h1>'
            .'<div class="wp-block-button"><a href="/link">Label</a><strong>Extra</strong></div>'
            .'<figure class="wp-block-table"><table><tr><td>Data</td><div>Unexpected</div></tr></table></figure>';

        self::assertSame((new SymfonyRichTextSanitizer())->sanitize($input), self::normalize($input));
    }

    private static function normalize(string $html): string
    {
        $document = new DOMDocument();
        $document->loadHTML('<html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $body = $document->getElementsByTagName('body')->item(0);
        self::assertInstanceOf(DOMElement::class, $body);
        foreach (iterator_to_array($body->childNodes) as $block) {
            if ($block instanceof DOMElement) {
                NormalizeWordPressTextBlock::normalize($block);
            }
        }

        $normalized = $document->saveHTML($body);
        self::assertIsString($normalized);

        return (new SymfonyRichTextSanitizer())->sanitize($normalized);
    }
}
