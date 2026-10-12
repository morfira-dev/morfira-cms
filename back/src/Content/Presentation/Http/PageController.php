<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\GetPage\GetPage;
use Cms\Content\Application\ListPages\ListPages;
use Cms\Content\Application\PageView;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Domain\PageSlugAlreadyExists;
use Cms\Content\Domain\PageVersionConflict;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PageController
{
    public function __construct(
        private CreatePage $createPage,
        private GetPage $getPage,
        private ListPages $listPages,
        private UpdatePage $updatePage,
    ) {
    }

    #[Route('/api/v1/admin/pages', name: 'content.pages.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $pages = ($this->listPages)();

        return new JsonResponse([
            'items' => array_map(static fn (PageView $page): array => $page->toArray(), $pages),
        ]);
    }

    #[Route('/api/v1/admin/pages/{id}', name: 'content.pages.get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        try {
            return new JsonResponse(($this->getPage)($id)->toArray());
        } catch (PageNotFound) {
            return new JsonResponse(['error' => 'Page not found.'], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/pages', name: 'content.pages.create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $title = $payload['title'] ?? null;
        $slug = $payload['slug'] ?? null;

        if (!is_string($title) || !is_string($slug)) {
            return new JsonResponse(['error' => 'Fields "title" and "slug" must be strings.'], 422);
        }

        try {
            $page = ($this->createPage)($title, $slug);
        } catch (PageSlugAlreadyExists $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new JsonResponse($page->toArray(), 201);
    }

    #[Route('/api/v1/admin/pages/{id}', name: 'content.pages.update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $title = $payload['title'] ?? null;
        $slug = $payload['slug'] ?? null;
        $blocks = $payload['blocks'] ?? null;
        $seo = $payload['seo'] ?? null;
        $version = $payload['version'] ?? null;

        if (!is_string($title) || !is_string($slug) || !is_array($blocks) || !array_is_list($blocks) || !is_array($seo) || array_is_list($seo) || !is_int($version)) {
            return new JsonResponse(['error' => 'Invalid page revision payload.'], 422);
        }

        $blockPayloads = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                return new JsonResponse(['error' => 'Every block must be an object.'], 422);
            }
            /** @var array<string, mixed> $block */
            $blockPayloads[] = $block;
        }

        try {
            /** @var array<string, mixed> $seo */
            $page = ($this->updatePage)($id, $title, $slug, $blockPayloads, PageSeo::fromArray($seo), $version);
        } catch (PageNotFound) {
            return new JsonResponse(['error' => 'Page not found.'], 404);
        } catch (PageSlugAlreadyExists|PageVersionConflict $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new JsonResponse($page->toArray());
    }
}
