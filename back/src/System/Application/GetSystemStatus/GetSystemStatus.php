<?php

declare(strict_types=1);

namespace Cms\System\Application\GetSystemStatus;

final readonly class GetSystemStatus
{
    public function __invoke(): SystemStatus
    {
        return SystemStatus::healthy();
    }
}
