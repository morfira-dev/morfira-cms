<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Html;

use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use PHPUnit\Framework\TestCase;

final class SymfonyRichTextSanitizerTest extends TestCase
{
    private const string ID = 'd93352b7-3f4e-461a-a7f0-eca56fc42302';

    public function testPreservesEditorMarkupAndIsIdempotent(): void
    {
        $sanitizer = new SymfonyRichTextSanitizer();
        $image = '/media/'.self::ID.'/640w.webp';
        $html = $sanitizer->sanitize('<h1>Guide</h1><h6>Note</h6><hr><p dir="rtl"><sub>2</sub><sup>3</sup>'
            .'<span style="color: rgb(0, 80, 140); font-family: Arial, sans-serif; font-size: 18px">Text</span>'
            .'<span data-mw-node="morfira:mention" data-mention-id="member-1">@Writer</span></p>'
            .'<ol start="+3" reversed style="list-style-type: upper-roman"><li data-checklist-checked="true">Done</li></ol>'
            .'<table style="width: 75%"><caption>Results</caption><colgroup><col span="2" style="width: 50%"></colgroup>'
            .'<thead><tr><th scope="colgroup" colspan="2">Group</th></tr></thead><tbody><tr><td rowspan="2" style="padding: 6px; border: 2px solid #123456">A</td></tr><tr><td>B</td></tr></tbody></table>'
            .'<figure><img src="'.$image.'" width="640" alt="Sample"><figcaption>Caption</figcaption></figure>'
            .'<img src="https://images.example.test/picture.png" alt="External">');

        self::assertStringContainsString('<h1>Guide</h1><h6>Note</h6><hr />', $html);
        self::assertStringContainsString('<p dir="rtl"><sub>2</sub><sup>3</sup>', $html);
        self::assertStringContainsString('style="color: rgb(0, 80, 140); font-family: Arial, sans-serif; font-size: 18px"', $html);
        self::assertStringContainsString('data-mw-node="morfira:mention" data-mention-id="member-1"', $html);
        self::assertStringContainsString('<ol start="3" reversed style="list-style-type: upper-roman"><li data-checklist-checked="true">Done</li></ol>', $html);
        self::assertStringContainsString('<table style="width: 75%"><caption>Results</caption><colgroup><col span="2" style="width: 50%" />', $html);
        self::assertStringContainsString('<th scope="colgroup" colspan="2">Group</th>', $html);
        self::assertStringContainsString('<td rowspan="2" style="padding: 6px; border: 2px solid #123456">A</td>', $html);
        self::assertStringContainsString('<figure><img src="'.$image.'" width="640" alt="Sample" /><figcaption>Caption</figcaption></figure>', $html);
        self::assertStringContainsString('<img src="https://images.example.test/picture.png" alt="External" />', $html);
        self::assertSame($html, $sanitizer->sanitize($html));
    }

    public function testRejectsUnsupportedMarkupAttributesAndImageSources(): void
    {
        $sanitizer = new SymfonyRichTextSanitizer();
        $public = '/media/'.self::ID.'/640w.webp';
        $private = '/api/v1/admin/media/'.self::ID.'/variants/640w.webp';
        $html = $sanitizer->sanitize('<p dir="auto" style="position: fixed; background-image: url(https://tracker.test); color: teal" onclick="alert(1)">Preview</p>'
            .'<span data-mw-node="unsupported" data-mention-id="">Unsafe metadata</span><svg><text>Rejected</text></svg>'
            .'<ul><li data-checklist-checked="other">Plain item</li></ul><img src="'.$private.'" alt="">'
            .'<img src="http://tracker.test/image.png"><img src="//tracker.test/image.png">'
            .'<img src="https://user:password@tracker.test/image.png"><img src="https://127.0.0.1/image.png">'
            .'<img src="https://tracker.test/elsewhere/../api%2Fv1%2Fadmin%2Fmedia/'.self::ID.'/variants/640w.webp">'
            .'<img src="'.$public.'?unexpected=1"><img src="/media/'.self::ID.'/../640w.webp">'
            .'<img src="'.$public.'" srcset="https://tracker.test/alternate.png 2x" onerror="alert(1)">');

        self::assertSame('<p style="color: teal">Preview</p><span>Unsafe metadata</span><ul><li>Plain item</li></ul><img src="'.$public.'" />', $html);
    }

    public function testRetainsOnlyBoundedHeadingFragmentIdentifiers(): void
    {
        $sanitizer = new SymfonyRichTextSanitizer();
        $html = $sanitizer->sanitize('<h2 id="read-more">Guide</h2><h3 id="раздел.2">Part</h3>'
            .'<p id="not-a-heading">Text</p><h4 id="has whitespace">Invalid</h4>'
            .'<h5 id="'.str_repeat('a', 129).'">Too long</h5>');

        self::assertSame('<h2 id="read-more">Guide</h2><h3 id="раздел.2">Part</h3><p>Text</p><h4>Invalid</h4><h5>Too long</h5>', $html);
        self::assertSame($html, $sanitizer->sanitize($html));
    }
}
