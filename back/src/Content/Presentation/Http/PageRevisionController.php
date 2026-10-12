<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\ListPageRevisions;
use Cms\Content\Application\RestorePageRevision;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRevisionNotFound;
use Cms\Content\Domain\PageSlugAlreadyExists;
use Cms\Content\Domain\PageVersionConflict;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PageRevisionController
{
    public function __construct(private ListPageRevisions $list, private RestorePageRevision $restore)
    {
    }

    #[Route('/api/v1/admin/pages/{id}/revisions', name: 'content.pages.revisions.list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        try {
            return new JsonResponse(['items' => array_map(static fn ($revision): array => $revision->toArray(), ($this->list)($id))]);
        } catch (PageNotFound) {
            return new JsonResponse(['error' => 'Page not found.'], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/pages/{id}/revisions/{revision}/restore', name: 'content.pages.revisions.restore', requirements: ['revision' => '\\d+'], methods: ['POST'])]
    public function restore(string $id, int $revision, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        if (array_keys($payload) !== ['version'] || !is_int($payload['version'])) {
            return new JsonResponse(['error' => 'A current integer version is required.'], 422);
        }

        try {
            return new JsonResponse(($this->restore)($id, $revision, $payload['version'])->toArray());
        } catch (PageNotFound|PageRevisionNotFound $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (PageSlugAlreadyExists|PageVersionConflict $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }
}
