<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\DeleteEntry;
use Cms\Content\Application\DeletePage;
use Cms\Content\Domain\EntryDeletionBlocked;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\PageNotFound;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ContentDeletionController
{
    public function __construct(private DeletePage $pages, private DeleteEntry $entries)
    {
    }

    #[Route('/api/v1/admin/pages/{id}', name: 'content.pages.delete', methods: ['DELETE'])]
    public function page(string $id): Response
    {
        try {
            ($this->pages)($id);
        } catch (PageNotFound) {
            return new JsonResponse(['error' => 'Page not found.'], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/v1/admin/entries/{id}', name: 'content.entries.delete', methods: ['DELETE'])]
    public function entry(string $id): Response
    {
        try {
            ($this->entries)($id);
        } catch (EntryNotFound) {
            return new JsonResponse(['error' => 'Entry not found.'], 404);
        } catch (EntryDeletionBlocked $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
