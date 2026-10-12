<?php

declare(strict_types=1);

namespace Cms\Theme\Application;

interface ThemeCatalog
{
    /** @return list<ThemeView> */
    public function all(): array;

    public function supports(string $theme, string $template): bool;

    public function templatePath(string $theme, string $template): string;
}
