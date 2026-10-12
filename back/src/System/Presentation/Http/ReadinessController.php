<?php

declare(strict_types=1);

namespace Cms\System\Presentation\Http;

use Cms\System\Application\GetReadiness\GetReadiness;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ReadinessController
{
    public function __construct(private GetReadiness $getReadiness)
    {
    }

    #[Route('/ready', name: 'system.ready', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $status = ($this->getReadiness)();

        return new JsonResponse([
            'status' => $status->ready ? 'ready' : 'unavailable',
            'service' => 'cms',
            'database' => $status->ready ? 'ready' : 'unavailable',
        ], $status->ready ? 200 : 503);
    }
}
