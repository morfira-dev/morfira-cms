<?php

declare(strict_types=1);

namespace Cms\System\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%env(bool:MAINTENANCE_MODE)%')]
        private bool $enabled,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 256]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->enabled) {
            return;
        }

        $request = $event->getRequest();
        if (in_array($request->getPathInfo(), ['/health', '/ready'], true)) {
            return;
        }

        $response = str_starts_with($request->getPathInfo(), '/api/') || $request->getPreferredFormat() === 'json'
            ? new JsonResponse(['error' => 'Morfira is temporarily unavailable for maintenance.'], 503)
            : new Response('<!doctype html><html lang="en"><meta charset="utf-8"><title>Maintenance</title><h1>Temporarily unavailable</h1><p>Morfira is undergoing maintenance. Please try again shortly.</p>', 503, ['Content-Type' => 'text/html; charset=UTF-8']);
        $response->headers->set('Retry-After', '300');
        $response->headers->set('Cache-Control', 'no-store');
        $event->setResponse($response);
    }
}
