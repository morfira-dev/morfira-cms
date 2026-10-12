<?php

declare(strict_types=1);

namespace Cms\System\Presentation\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class OpenApiController
{
    #[Route('/api/openapi.yaml', name: 'system.openapi', methods: ['GET'])]
    public function __invoke(): Response
    {
        $schema = file_get_contents(dirname(__DIR__, 4) . '/openapi/openapi.yaml');

        if (!is_string($schema)) {
            throw new \RuntimeException('The OpenAPI schema could not be read.');
        }

        return new Response($schema, headers: [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
