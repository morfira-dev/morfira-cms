<?php

declare(strict_types=1);

namespace Cms\System\Application\GetSystemStatus;

final readonly class SystemStatus
{
    private function __construct(
        public string $status,
        public string $service,
    ) {
    }

    public static function healthy(): self
    {
        return new self(
            status: 'ok',
            service: 'cms',
        );
    }
}
