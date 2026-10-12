<?php

declare(strict_types=1);

namespace Cms\Theme\Presentation\Http;

use Cms\Theme\Application\ThemeCatalog;
use Cms\Theme\Application\ThemeView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ThemeController
{
    public function __construct(private ThemeCatalog $themes)
    {
    }

    #[Route('/api/v1/admin/themes', name: 'themes.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['items' => array_map(static fn (ThemeView $theme): array => $theme->toArray(), $this->themes->all())]);
    }
}
