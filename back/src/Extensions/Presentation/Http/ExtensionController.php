<?php

declare(strict_types=1);

namespace Cms\Extensions\Presentation\Http;

use Cms\Extensions\Application\ExtensionCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ExtensionController
{
    public function __construct(private ExtensionCatalog $extensions)
    {
    }

    #[Route('/api/v1/admin/extensions', name: 'extensions.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'apiVersion' => 1,
            'items' => array_map(static fn ($manifest): array => $manifest->toArray(), $this->extensions->all()),
        ]);
    }
}
