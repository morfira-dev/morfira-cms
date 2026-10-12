<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Publishing\Application\GetPublishedEntry;
use Cms\Publishing\Application\ListPublishedEntries;
use Cms\Schema\Domain\ContentTypeNotFound;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class PublicEntryController
{
    public function __construct(private GetPublishedEntry $getEntry, private ListPublishedEntries $listEntries, private Environment $twig)
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

    #[Route('/content/{type}/taxonomy/{taxonomy}/{term}', name: 'publishing.entries.taxonomy_archive', requirements: ['type' => '[a-z][a-z0-9]*(?:-[a-z0-9]+)*', 'taxonomy' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'term' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function archive(string $type, string $taxonomy, string $term, Request $request): Response
    {
        $pageValue = $request->query->get('page', '1');
        if (preg_match('/^[1-9][0-9]*$/', $pageValue) !== 1) return new Response('Invalid archive page.', 422);
        $pageNumber = (int) $pageValue;
        $result = ($this->listEntries)($type, 20, ($pageNumber - 1) * 20, $taxonomy, $term);
        if ($result['total'] === 0 || $result['items'] === []) return new Response('Taxonomy archive not found.', 404);
        $first = $result['items'][0];
        $descriptor = array_find($first->taxonomies, static fn (array $item): bool => $item['taxonomySlug'] === $taxonomy && $item['termSlug'] === $term);
        $termName = $descriptor['name'] ?? $term;
        $etag = hash('sha256', implode(':', array_map(static fn ($entry): string => $entry->snapshotId, $result['items'])).':'.$result['total'].':'.$pageNumber);
        $response = new Response($this->twig->render('delivery/taxonomy_archive.html.twig', ['type' => $type, 'taxonomy' => $taxonomy, 'term' => $term, 'termName' => $termName, 'page' => $pageNumber, 'result' => $result]));
        $response->setPublic();$response->setMaxAge(60);$response->setSharedMaxAge(300);$response->setEtag($etag);$response->isNotModified($request);

        return $response;
    }
}
