<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Presentation\Http;

use Cms\Publishing\Application\ResolveImportedRedirect;
use Cms\Publishing\Domain\ImportedRedirectRepository;
use Cms\Publishing\Presentation\Http\ImportedRedirectSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ImportedRedirectSubscriberTest extends TestCase
{
    public function testLiveNativeRouteTakesPrecedence(): void
    {
        $repo = $this->createMock(ImportedRedirectRepository::class);
        $repo->expects(self::never())->method('activeTargetPath');
        $event = $this->event('/older', 200);
        new ImportedRedirectSubscriber(new ResolveImportedRedirect($repo))($event);

        self::assertSame(200, $event->getResponse()->getStatusCode());
    }

    public function testMissingNativeRouteRedirectsHeadToLocalTargetWithoutCaching(): void
    {
        $repo = $this->createMock(ImportedRedirectRepository::class);
        $repo->expects(self::once())->method('activeTargetPath')->with('/2022/original')->willReturn('/content/wordpress-posts/new');
        $event = $this->event('/2022/original/', 404, 'HEAD');
        new ImportedRedirectSubscriber(new ResolveImportedRedirect($repo))($event);

        self::assertSame(301, $event->getResponse()->getStatusCode());
        self::assertSame('/content/wordpress-posts/new', $event->getResponse()->headers->get('Location'));
        self::assertTrue($event->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    public function testExactLegacyHomeQueryCanResolveWithAnActiveHomePage(): void
    {
        $repo = $this->createMock(ImportedRedirectRepository::class);
        $repo->expects(self::once())->method('activeTargetPath')->with('/?p=42')->willReturn('/post');
        $event = $this->event('/?p=42', 200);
        $event->getRequest()->attributes->set('_route', 'publishing.pages.home');
        new ImportedRedirectSubscriber(new ResolveImportedRedirect($repo))($event);

        self::assertSame('/post', $event->getResponse()->headers->get('Location'));
    }

    private function event(string $uri, int $status, string $method = 'GET'): ResponseEvent
    {
        return new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($uri, $method),
            HttpKernelInterface::MAIN_REQUEST,
            new Response('', $status),
        );
    }
}
