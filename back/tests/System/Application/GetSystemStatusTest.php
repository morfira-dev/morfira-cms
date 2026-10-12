<?php

declare(strict_types=1);

namespace Cms\Tests\System\Application;

use Cms\System\Application\GetSystemStatus\GetSystemStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetSystemStatusTest extends TestCase
{
    #[Test]
    public function it_reports_a_healthy_system(): void
    {
        $status = (new GetSystemStatus())();

        self::assertSame('ok', $status->status);
        self::assertSame('cms', $status->service);
    }
}
