<?php

declare(strict_types=1);

namespace Cms\Navigation\Presentation\Http;

use Cms\Navigation\Application\GetPublishedNavigation\GetPublishedNavigation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PublicNavigationController
{
    public function __construct(private GetPublishedNavigation $getPublishedNavigation) {}

    #[Route('/api/v1/content/navigation/{code}', name: 'navigation.public.get', methods: ['GET'])]
    public function get(string $code, Request $request): Response
    {
        try { $menu = ($this->getPublishedNavigation)($code); }
        catch (\InvalidArgumentException) { return new JsonResponse(['error' => 'Published navigation menu not found.'], 404); }
        if ($menu === null) { return new JsonResponse(['error' => 'Published navigation menu not found.'], 404); }
        $response = new JsonResponse($menu->toArray());
        $response->setPublic();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(300);
        $response->setEtag($menu->snapshotId);
        $response->isNotModified($request);
        return $response;
    }
}
