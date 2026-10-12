<?php

declare(strict_types=1);

namespace Cms\System\Application\GetReadiness;

final readonly class ReadinessStatus
{
    private function __construct(public bool $ready)
    {
    }

    public static function ready(): self
    {
        return new self(true);
    }

    public static function unavailable(): self
    {
        return new self(false);
    }
}
