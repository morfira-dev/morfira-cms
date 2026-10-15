<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\ListEntryRevisions;
use Cms\Content\Application\RestoreEntryRevision;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRevisionNotFound;
use Cms\Content\Domain\EntrySlugAlreadyExists;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Schema\Domain\ContentTypeNotFound;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class EntryRevisionController
{
    public function __construct(private ListEntryRevisions $list, private RestoreEntryRevision $restore)
    {
    }

    #[Route('/api/v1/admin/entries/{id}/revisions', name: 'content.entries.revisions.list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        try {
            return new JsonResponse(['items' => array_map(static fn ($revision): array => $revision->toArray(), ($this->list)($id))]);
        } catch (EntryNotFound) {
            return new JsonResponse(['error' => 'Entry not found.'], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/entries/{id}/revisions/{revision}/restore', name: 'content.entries.revisions.restore', requirements: ['revision' => '\d+'], methods: ['POST'])]
    public function restore(string $id, int $revision, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        if (array_keys($payload) !== ['version'] || !is_int($payload['version'])) {
            return new JsonResponse(['error' => 'A current integer version is required.'], 422);
        }
        try {
            return new JsonResponse(($this->restore)($id, $revision, $payload['version'])->toArray());
        } catch (EntryNotFound|EntryRevisionNotFound|ContentTypeNotFound $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (EntrySlugAlreadyExists|EntryVersionConflict $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }
}
