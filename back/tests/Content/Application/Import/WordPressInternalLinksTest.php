<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\Import\WordPressInternalLinks;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use PHPUnit\Framework\TestCase;

final class WordPressInternalLinksTest extends TestCase
{
    public function testRewritesOnlyKnownAnchorsAndPreservesEscapedFragments(): void
    {
        $links = WordPressInternalLinks::index('https://wordpress.example/blog', [
            ['url' => 'http://WordPress.example/blog/News/', 'path' => '/content/wordpress-posts/news'],
            ['url' => 'https://wordpress.example/?page_id=7', 'path' => '/about'],
            ['url' => 'https://wordpress.example/', 'path' => '/'],
        ]);
        $html = (new SymfonyRichTextSanitizer())->sanitize(
            '<p><a title="name href=&quot;kept&quot;" href="https://WORDPRESS.example/blog/News#first+part&amp;next=3">News</a> '
            .'<a href="http://wordpress.example/?page_id=7#">About</a> <a href="https://wordpress.example">Home</a> '
            .'<a href="https://wordpress.example/blog/news">Different case</a> <a href="/blog/News">Relative</a> '
            .'https://wordpress.example/blog/News</p>',
        );
        $expected = (new SymfonyRichTextSanitizer())->sanitize(
            '<p><a title="name href=&quot;kept&quot;" href="/content/wordpress-posts/news#first+part&amp;next=3">News</a> '
            .'<a href="/about#">About</a> <a href="/">Home</a> '
            .'<a href="https://wordpress.example/blog/news">Different case</a> <a href="/blog/News">Relative</a> '
            .'https://wordpress.example/blog/News</p>',
        );

        $rewritten = WordPressInternalLinks::rewrite($html, $links);

        self::assertSame($expected, (new SymfonyRichTextSanitizer())->sanitize($rewritten));
        self::assertSame('<a href="/about#notes">About</a>', WordPressInternalLinks::rewrite(
            '<a href="https://wordpress.example/?page_id&#61;7&#35;notes">About</a>',
            $links,
        ));
    }

    public function testLeavesAmbiguousUnknownAndUnsupportedLinksUnchanged(): void
    {
        $links = WordPressInternalLinks::index('https://wordpress.example:8443', [
            ['url' => 'https://wordpress.example:8443/duplicate', 'path' => '/first'],
            ['url' => 'http://wordpress.example:8443/duplicate/', 'path' => null],
            ['url' => 'https://wordpress.example:8443/protected', 'path' => null],
            ['url' => 'https://wordpress.example:8443/supported', 'path' => '/supported'],
            ['url' => 'https://wordpress.example:8443/?p=47', 'path' => '/post'],
            ['url' => 'https://wordpress.example:8443/encoded%2Fslash', 'path' => '/invalid'],
            ['url' => 'https://wordpress.example:8443/folder/%2e%2e/path', 'path' => '/invalid'],
            ['url' => 'https://wordpress.example:8443/bad-output', 'path' => '//other.example'],
        ]);
        $html = '<p><a href="https://wordpress.example:8443/duplicate">1</a> '
            .'<a href="https://wordpress.example:8443/protected">2</a> '
            .'<a href="https://wordpress.example/supported">3</a> '
            .'<a href="https://other.example:8443/supported">4</a> '
            .'<a href="https://wordpress.example:8443/supported?extra&#61;1">5</a> '
            .'<a href="https://wordpress.example:8443/?p&#61;47&amp;extra&#61;1">6</a> '
            .'<a href="//wordpress.example:8443/supported">7</a> '
            .'<a href="http://user&#64;wordpress.example:8443/supported">8</a> '
            .'<a href="https://wordpress.example:8443/encoded%2Fslash">9</a> '
            .'<a href="https://wordpress.example:8443/folder/%2e%2e/path">10</a> '
            .'<a href="https://wordpress.example:8443/bad-output">11</a></p>';

        self::assertSame($html, WordPressInternalLinks::rewrite($html, $links));
        self::assertSame('<a href="/post#notes">Post</a>', WordPressInternalLinks::rewrite(
            '<a href="https://wordpress.example:8443/?p&#61;47#notes">Post</a>',
            $links,
        ));
    }

    public function testKeepsOriginalMarkupWhenRewritingExceedsTheRichTextLimit(): void
    {
        $links = WordPressInternalLinks::index('https://wp.test', [
            ['url' => 'https://wp.test/a', 'path' => '/long-article-slug'],
        ]);
        $html = '<a href="https://wp.test/a#part&#43;two">Article</a>';
        $fullHtml = $html.str_repeat('я', 20_000 - mb_strlen($html));

        self::assertSame($fullHtml, WordPressInternalLinks::rewrite($fullHtml, $links));
        self::assertSame([], WordPressInternalLinks::index(null, [['url' => 'https://wp.test/a', 'path' => '/article']]));
    }
}
