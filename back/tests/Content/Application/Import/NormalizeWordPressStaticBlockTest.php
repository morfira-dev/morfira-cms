<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\Import\SegmentWordPressImages;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use PHPUnit\Framework\TestCase;

final class NormalizeWordPressStaticBlockTest extends TestCase
{
    public function testRenderedHeadingsUseSupportedLevels(): void
    {
        $html = self::normalize(
            '<h1 class="wp-block-site-title">Site title</h1>'
            .'<h1 class="wp-block-post-title"><a href="/post">Post title</a></h1>'
            .'<h5 class="wp-block-query-title">Search results</h5>'
            .'<h6 class="wp-block-comments-title">Comments</h6>'
            .'<h3 class="wp-block-post-comments-title">Existing heading</h3>',
        );

        self::assertStringContainsString('<h2>Site title</h2><h2><a href="/post" rel="noopener noreferrer">Post title</a></h2>', $html);
        self::assertStringContainsString('<h4>Search results</h4><h4>Comments</h4><h3>Existing heading</h3>', $html);
    }

    public function testCodeAndPreformattedBlocksKeepVisibleLines(): void
    {
        $html = self::normalize(
            '<pre class="wp-block-code"><code>First'."\r\n".'&lt;Second&gt;<script>hidden script</script></code></pre>'
            .'<pre class="wp-block-preformatted">Line'."\n".'<em>Emphasis'."\n\n".'Final</em><style>hidden style</style></pre>',
        );

        self::assertStringContainsString('<p><code>First<br />&lt;Second&gt;</code></p>', $html);
        self::assertStringContainsString('<p>Line<br /><em>Emphasis<br /><br />Final</em></p>', $html);
        self::assertStringNotContainsString('hidden', $html);
    }

    public function testArchiveAndCategoryOptionsBecomeOrderedSafeLinks(): void
    {
        $html = self::normalize(
            '<div class="wp-block-archives-dropdown"><select onchange="discard()">'
            .'<option value="">Choose an archive</option>'
            .'<option value="https://example.test/2026/01/">January 2026</option>'
            .'<option value="/2026/02/" disabled>Disabled month</option>'
            .'<optgroup label="Upcoming"><option value="/2026/03/" label="March &amp; April">March</option></optgroup>'
            .'<optgroup label="Closed" disabled><option value="/closed/">Closed month</option></optgroup></select></div>'
            .'<select class="wp-block-categories-dropdown">'
            .'<option value="42">Numeric category</option><option value="//example.test/">Protocol-relative category</option>'
            .'<option value="/%2Fevil.test/">Encoded authority</option><option value="javascript:discard()">Script category</option>'
            .'<option value="https://user:password@example.test/">Credential category</option>'
            .'<option value="/category/news/">News</option></select>',
        );

        self::assertStringContainsString(
            '<ul><li><a href="https://example.test/2026/01/" rel="noopener noreferrer">January 2026</a></li>'
            .'<li><a href="/2026/03/" rel="noopener noreferrer">March &amp; April</a></li></ul>'
            .'<ul><li><a href="/category/news/" rel="noopener noreferrer">News</a></li></ul>',
            $html,
        );
        self::assertStringNotContainsString('discard()', $html);
        self::assertStringNotContainsString('category</', $html);
        self::assertStringNotContainsString('month</', $html);
        self::assertStringNotContainsString('Encoded authority', $html);
        self::assertStringNotContainsString('Choose an archive', $html);
    }

    public function testUnrecognizedBlocksAndUrlLessDropdownsUseExistingFallback(): void
    {
        $input = '<h1 class="custom-wp-block-site-title">A heading</h1>'
            .'<pre class="classic-preformatted">Original'."\n".'lines</pre>'
            .'<div class="wp-block-categories"><select><option value="12">Existing category</option></select></div>';

        self::assertSame((new SymfonyRichTextSanitizer())->sanitize($input), self::normalize($input));
    }

    public function testPreservesPreformattedWhitespaceWhenLineBreaksWouldNotBeEnough(): void
    {
        $html = self::normalize(
            '<pre class="wp-block-code"><code>First'."\n    ".'Second</code></pre>'
            .'<pre class="wp-block-preformatted">Left  right'."\t".'end</pre>',
        );

        self::assertSame('<pre><code>First'."\n    ".'Second</code></pre><pre>Left  right'."\t".'end</pre>', $html);
    }

    private static function normalize(string $html): string
    {
        $segment = new SegmentWordPressImages(new SymfonyRichTextSanitizer());
        $result = $segment($html, static fn (): never => throw new \LogicException('No image resolution expected.'));
        $block = $result['segments'][0];
        self::assertSame('richText', $block['type']);

        return $block['html'];
    }
}
