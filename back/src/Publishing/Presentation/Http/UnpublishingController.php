<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\PageNotFound;
use Cms\Publishing\Application\UnpublishEntry;
use Cms\Publishing\Application\UnpublishPage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class UnpublishingController
{
    public function __construct(private UnpublishPage $pages, private UnpublishEntry $entries)
    {
    }

    #[Route('/api/v1/admin/pages/{id}/publish', name: 'publishing.pages.unpublish', methods: ['DELETE'])]
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

    #[Route('/api/v1/admin/entries/{id}/publish', name: 'publishing.entries.unpublish', methods: ['DELETE'])]
    public function entry(string $id): Response
    {
        try {
            ($this->entries)($id);
        } catch (EntryNotFound) {
            return new JsonResponse(['error' => 'Entry not found.'], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
