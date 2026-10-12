<?php

declare(strict_types=1);

namespace Cms\System\Presentation\Http;

use Cms\System\Application\GetSystemStatus\GetSystemStatus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(
        private GetSystemStatus $getSystemStatus,
    ) {
    }

    #[Route('/health', name: 'system.health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $status = ($this->getSystemStatus)();

        return new JsonResponse([
            'status' => $status->status,
            'service' => $status->service,
        ]);
    }
}
