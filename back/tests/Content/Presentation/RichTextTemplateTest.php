<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Presentation;

use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use Cms\Content\Infrastructure\Twig\RichTextExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class RichTextTemplateTest extends TestCase
{
    #[Test]
    #[DataProvider('templates')]
    public function it_renders_sanitized_formatting_without_double_escaping(string $template): void
    {
        $sanitizer = new SymfonyRichTextSanitizer();
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3).'/templates'));
        $twig->addExtension(new RichTextExtension($sanitizer));
        $twig->addFunction(new TwigFunction('media_asset', static fn (string $id): null => null));

        $html = $twig->render($template, [
            'page' => [
                'title' => 'Landing',
                'version' => 2,
                'seo' => [
                    'title' => null,
                    'description' => null,
                    'noIndex' => false,
                    'openGraphImageAssetId' => null,
                ],
                'blocks' => [[
                    'type' => 'richText',
                    'data' => [
                        'content' => '<h2 onclick="alert(1)">Welcome</h2><p><strong>Safe</strong> copy.</p><script>alert(1)</script><a href="javascript:alert(1)">Bad link</a>',
                    ],
                ]],
            ],
            'canonicalUrl' => null,
            'notFound' => false,
            'publicSiteUrl' => 'https://example.test',
        ]);

        self::assertStringContainsString('<h2>Welcome</h2>', $html);
        self::assertStringContainsString('<strong>Safe</strong>', $html);
        self::assertStringContainsString('<a rel="noopener noreferrer">Bad link</a>', $html);
        self::assertStringNotContainsString('&lt;h2', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    /** @return iterable<string, array{string}> */
    public static function templates(): iterable
    {
        yield 'draft preview' => ['content/page.html.twig'];
        yield 'public delivery' => ['delivery/page.html.twig'];
    }
}
