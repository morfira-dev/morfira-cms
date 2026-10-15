<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\ListEntryReferences;
use Cms\Content\Domain\EntryNotFound;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class EntryReferenceController
{
    public function __construct(private ListEntryReferences $references)
    {
    }

    #[Route('/api/v1/admin/entries/{id}/references', name: 'content.entries.references', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        try {
            return new JsonResponse(['items' => ($this->references)($id)]);
        } catch (EntryNotFound) {
            return new JsonResponse(['error' => 'Entry not found.'], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }
}
