<?php

declare(strict_types=1);

namespace Cms\Theme\Infrastructure;

use Cms\Theme\Application\ThemeCatalog;
use Cms\Theme\Application\ThemeView;

final readonly class BuiltInThemeCatalog implements ThemeCatalog
{
    private const THEMES = [
        'morfira' => [
            'name' => 'Morfira',
            'version' => '1.0.0',
            'templates' => ['standard' => 'Standard', 'wide' => 'Wide'],
            'path' => 'delivery/page.html.twig',
        ],
        'atelier' => [
            'name' => 'Atelier',
            'version' => '1.0.0',
            'templates' => ['editorial' => 'Editorial', 'showcase' => 'Showcase'],
            'path' => 'delivery/themes/atelier/page.html.twig',
        ],
    ];

    public function all(): array
    {
        $themes = [];
        foreach (self::THEMES as $id => $theme) {
            $templates = [];
            foreach ($theme['templates'] as $templateId => $name) {
                $templates[] = ['id' => $templateId, 'name' => $name];
            }
            $themes[] = new ThemeView($id, $theme['name'], $theme['version'], $templates, ['header', 'content', 'footer']);
        }

        return $themes;
    }

    public function supports(string $theme, string $template): bool
    {
        return isset(self::THEMES[$theme]['templates'][$template]);
    }

    public function templatePath(string $theme, string $template): string
    {
        if (!$this->supports($theme, $template)) {
            throw new \InvalidArgumentException('Unsupported theme or template.');
        }

        return self::THEMES[$theme]['path'];
    }
}
