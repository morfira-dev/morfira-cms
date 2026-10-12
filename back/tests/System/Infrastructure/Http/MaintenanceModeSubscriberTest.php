<?php

declare(strict_types=1);

namespace Cms\Tests\System\Infrastructure\Http;

use Cms\System\Infrastructure\Http\MaintenanceModeSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class MaintenanceModeSubscriberTest extends TestCase
{
    #[Test]
    public function it_blocks_application_traffic_but_preserves_health_checks(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $subscriber = new MaintenanceModeSubscriber(true);
        $apiEvent = new RequestEvent($kernel, Request::create('/api/v1/content/pages/home'), HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onRequest($apiEvent);

        self::assertTrue($apiEvent->hasResponse());
        self::assertSame(503, $apiEvent->getResponse()->getStatusCode());
        self::assertSame('300', $apiEvent->getResponse()->headers->get('Retry-After'));

        $healthEvent = new RequestEvent($kernel, Request::create('/health'), HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onRequest($healthEvent);
        self::assertNull($healthEvent->getResponse());
    }
}
