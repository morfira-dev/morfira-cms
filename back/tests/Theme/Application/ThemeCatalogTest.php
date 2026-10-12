<?php

declare(strict_types=1);

namespace Cms\Tests\Theme\Application;

use Cms\Theme\Infrastructure\BuiltInThemeCatalog;
use PHPUnit\Framework\TestCase;

final class ThemeCatalogTest extends TestCase
{
    public function test_it_exposes_only_registered_theme_templates(): void
    {
        $catalog = new BuiltInThemeCatalog();

        self::assertTrue($catalog->supports('morfira', 'wide'));
        self::assertTrue($catalog->supports('atelier', 'editorial'));
        self::assertFalse($catalog->supports('atelier', 'standard'));
        self::assertCount(2, $catalog->all());
        self::assertSame('delivery/themes/atelier/page.html.twig', $catalog->templatePath('atelier', 'showcase'));
        $this->expectException(\InvalidArgumentException::class);
        $catalog->templatePath('../unsafe', 'template');
    }
}
