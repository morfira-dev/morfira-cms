<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Publishing\Application\GetPublishedEntry;
use Cms\Schema\Domain\ContentTypeNotFound;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class PublicEntryController
{
    public function __construct(private GetPublishedEntry $getEntry, private Environment $twig)
    {
    }

    #[Route('/content/{type}/{slug}', name: 'publishing.entries.web', requirements: ['type' => '[a-z][a-z0-9]*(?:-[a-z0-9]+)*', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function get(string $type, string $slug, Request $request): Response
    {
        try {
            $entry = ($this->getEntry)($type, $slug);
        } catch (ContentTypeNotFound|\InvalidArgumentException) {
            return new Response('Content entry not found.', 404);
        }
        if (null === $entry) {
            return new Response('Content entry not found.', 404);
        }
        $response = new Response($this->twig->render('delivery/entry.html.twig', ['entry' => $entry]));
        $response->setPublic();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(300);
        $response->setEtag($entry->snapshotId);
        $response->isNotModified($request);

        return $response;
    }
}
