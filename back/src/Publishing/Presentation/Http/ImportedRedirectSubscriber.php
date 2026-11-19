<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Publishing\Application\ResolveImportedRedirect;
use Cms\Publishing\Domain\ImportedRedirectSource;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: 10)]
final readonly class ImportedRedirectSubscriber
{
    public function __construct(private ResolveImportedRedirect $resolve)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }
        $path = $request->getPathInfo();
        $query = $request->server->get('QUERY_STRING');
        $query = is_string($query) ? $query : null;
        if ($event->getResponse()->getStatusCode() !== 404 && !($path === '/' && ImportedRedirectSource::isLegacyQuery($query)
            && $request->attributes->get('_route') === 'publishing.pages.home')) {
            return;
        }

        $target = ($this->resolve)($path, $query);
        if ($target === null) {
            return;
        }

        $response = new RedirectResponse($target, 301);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $event->setResponse($response);
    }
}
