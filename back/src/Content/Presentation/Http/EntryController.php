<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\EntryView;
use Cms\Content\Application\GetEntry;
use Cms\Content\Application\ListEntries;
use Cms\Content\Application\UpdateEntry;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntrySlugAlreadyExists;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Domain\ContentTypeNotFound;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class EntryController
{
    public function __construct(private CreateEntry $createEntry, private GetEntry $getEntry, private ListEntries $listEntries, private UpdateEntry $updateEntry, private GetContentTypeDefinition $contentTypes)
    {
    }

    #[Route('/api/v1/admin/entries', name: 'content.entries.list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $id = $request->query->get('contentTypeId');
        if (!is_string($id)) {
            return new JsonResponse(['error' => 'Query parameter "contentTypeId" is required.'], 422);
        }
        try {
            \Cms\Schema\Domain\ContentTypeId::fromString($id);
            $this->contentTypes->byId($id);
        } catch (ContentTypeNotFound) {
            return new JsonResponse(['error' => 'Content type not found.'], 404);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(['items' => array_map(static fn (EntryView $entry): array => $entry->toArray(), ($this->listEntries)($id))]);
    }

    #[Route('/api/v1/admin/entries', name: 'content.entries.create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $unexpected = array_diff(array_keys($payload), ['contentTypeId', 'title', 'slug', 'values']);
        if ([] !== $unexpected) {
            return new JsonResponse(['error' => sprintf('Unexpected entry property "%s".', reset($unexpected))], 422);
        }
        $type = $payload['contentTypeId'] ?? null;
        $title = $payload['title'] ?? null;
        $slug = $payload['slug'] ?? null;
        $values = $payload['values'] ?? null;
        if (!is_string($type) || !is_string($title) || !is_string($slug) || !is_array($values) || ([] !== $values && array_is_list($values))) {
            return new JsonResponse(['error' => 'Invalid entry payload.'], 422);
        }
        try {
            /** @var array<string,mixed> $values */ $entry = ($this->createEntry)($type, $title, $slug, $values);
        } catch (ContentTypeNotFound) {
            return new JsonResponse(['error' => 'Content type not found.'], 404);
        } catch (EntrySlugAlreadyExists $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($entry->toArray(), 201);
    }

    #[Route('/api/v1/admin/entries/{id}', name: 'content.entries.get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        try {
            return new JsonResponse(($this->getEntry)($id)->toArray());
        } catch (EntryNotFound) {
            return new JsonResponse(['error' => 'Entry not found.'], 404);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/entries/{id}', name: 'content.entries.update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $unexpected = array_diff(array_keys($payload), ['title', 'slug', 'values', 'version']);
        if ([] !== $unexpected) {
            return new JsonResponse(['error' => sprintf('Unexpected entry property "%s".', reset($unexpected))], 422);
        }
        $title = $payload['title'] ?? null;
        $slug = $payload['slug'] ?? null;
        $values = $payload['values'] ?? null;
        $version = $payload['version'] ?? null;
        if (!is_string($title) || !is_string($slug) || !is_array($values) || ([] !== $values && array_is_list($values)) || !is_int($version)) {
            return new JsonResponse(['error' => 'Invalid entry revision payload.'], 422);
        }
        try {
            /** @var array<string,mixed> $values */ $entry = ($this->updateEntry)($id, $title, $slug, $values, $version);
        } catch (EntryNotFound|ContentTypeNotFound) {
            return new JsonResponse(['error' => 'Entry or content type not found.'], 404);
        } catch (EntrySlugAlreadyExists|EntryVersionConflict $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($entry->toArray());
    }
}
