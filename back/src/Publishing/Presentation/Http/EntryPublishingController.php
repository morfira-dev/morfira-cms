<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Publishing\Application\GetPublishedEntry;
use Cms\Publishing\Application\ListPublishedEntries;
use Cms\Publishing\Application\PublishedEntryView;
use Cms\Publishing\Application\PublishEntry;
use Cms\Publishing\Domain\PublishedEntrySlugAlreadyExists;
use Cms\Schema\Domain\ContentTypeNotFound;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class EntryPublishingController
{
    public function __construct(private PublishEntry $publishEntry, private GetPublishedEntry $getEntry, private ListPublishedEntries $listEntries)
    {
    }

    #[Route('/api/v1/admin/entries/{id}/publish', name: 'publishing.entries.publish', methods: ['POST'])]
    public function publish(string $id, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $unexpected = array_diff(array_keys($payload), ['version']);
        if ([] !== $unexpected) {
            return new JsonResponse(['error' => sprintf('Unexpected publication property "%s".', reset($unexpected))], 422);
        }
        $version = $payload['version'] ?? null;
        if (!is_int($version) || $version < 1) {
            return new JsonResponse(['error' => 'The current integer version is required.'], 422);
        }
        try {
            $entry = ($this->publishEntry)($id, $version);
        } catch (EntryNotFound|ContentTypeNotFound) {
            return new JsonResponse(['error' => 'Entry or content type not found.'], 404);
        } catch (EntryVersionConflict|PublishedEntrySlugAlreadyExists $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($entry->toArray());
    }

    #[Route('/api/v1/content/entries/{type}', name: 'publishing.entries.list', methods: ['GET'])]
    public function list(string $type, Request $request): JsonResponse
    {
        $limit = $this->integerQuery($request, 'limit', 20, 1, 100);
        $offset = $this->integerQuery($request, 'offset', 0, 0, PHP_INT_MAX);
        if (null === $limit || null === $offset) {
            return new JsonResponse(['error' => 'Invalid pagination parameters.'], 422);
        }
        $taxonomy = $request->query->get('taxonomy');
        $term = $request->query->get('term');
        if (($taxonomy === null) !== ($term === null)) {
            return new JsonResponse(['error' => 'taxonomy and term must be supplied together.'], 422);
        }
        $page = ($this->listEntries)($type, $limit, $offset, $taxonomy, $term);

        return new JsonResponse(['items' => array_map(static fn (PublishedEntryView $entry): array => $entry->toArray(), $page['items']), 'total' => $page['total'], 'limit' => $page['limit'], 'offset' => $page['offset']]);
    }

    #[Route('/api/v1/content/entries/{type}/{slug}', name: 'publishing.entries.get', methods: ['GET'])]
    public function get(string $type, string $slug, Request $request): Response
    {
        try {
            $entry = ($this->getEntry)($type, $slug);
        } catch (ContentTypeNotFound|\InvalidArgumentException) {
            return new JsonResponse(['error' => 'Published entry not found.'], 404);
        }
        if (null === $entry) {
            return new JsonResponse(['error' => 'Published entry not found.'], 404);
        }
        $response = new JsonResponse($entry->toArray());
        $response->setPublic();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(300);
        $response->setEtag($entry->snapshotId);
        $response->isNotModified($request);

        return $response;
    }

    private function integerQuery(Request $request, string $name, int $default, int $minimum, int $maximum): ?int
    {
        $value = $request->query->get($name, (string) $default);
        if (1 !== preg_match('/^(0|[1-9][0-9]*)$/', $value)) {
            return null;
        }
        $integer = (int) $value;

        return $integer >= $minimum && $integer <= $maximum ? $integer : null;
    }
}
