<?php

declare(strict_types=1);

namespace Cms\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class CsrfRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(private CsrfTokenManagerInterface $tokens)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['validate', 64]];
    }

    public function validate(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        if (preg_match('#^/api/v1/(?:admin|setup|auth/(?:login|logout|password))(?:/|$)#', $request->getPathInfo()) !== 1) {
            return;
        }

        $value = $request->headers->get('X-CSRF-Token', '');
        if (!$this->tokens->isTokenValid(new CsrfToken('cms_admin', $value))) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid CSRF token.'], 403));
        }
    }
}
