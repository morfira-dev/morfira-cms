<?php

declare(strict_types=1);

namespace Cms\Tests\System\Application;

use Cms\System\Application\GetReadiness\GetReadiness;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetReadinessTest extends TestCase
{
    #[Test]
    public function it_reports_database_availability_without_exposing_errors(): void
    {
        $readyConnection = $this->createMock(Connection::class);
        $readyConnection->method('fetchOne')->willReturn(1);
        self::assertTrue((new GetReadiness($readyConnection))()->ready);

        $failedConnection = $this->createMock(Connection::class);
        $failedConnection->method('fetchOne')->willThrowException(new \RuntimeException('secret connection details'));
        self::assertFalse((new GetReadiness($failedConnection))()->ready);
    }
}
