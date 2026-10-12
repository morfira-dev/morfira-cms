<?php

declare(strict_types=1);

namespace Cms\System\Application\GetReadiness;

use Doctrine\DBAL\Connection;

final readonly class GetReadiness
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(): ReadinessStatus
    {
        try {
            $result = $this->connection->fetchOne('SELECT 1');

            return $result === 1 || $result === '1'
                ? ReadinessStatus::ready()
                : ReadinessStatus::unavailable();
        } catch (\Throwable) {
            return ReadinessStatus::unavailable();
        }
    }
}
