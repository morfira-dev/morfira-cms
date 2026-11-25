<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\ManageReusableSections;
use Cms\Content\Application\PageSectionUsageLookup;
use Cms\Content\Application\RefreshSectionPlacements;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\ReusableSectionConflict;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SectionPlacementController
{
    public function __construct(
        private ManageReusableSections $sections,
        private PageSectionUsageLookup $usage,
        private RefreshSectionPlacements $refresh,
    ) {
    }

    #[Route('/api/v1/admin/sections/{id}/usage', name: 'content.sections.usage', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET'])]
    public function usage(string $id, Request $request): JsonResponse
    {
        $query = $request->query->all();
        $limit = filter_var($query['limit'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $offset = filter_var($query['offset'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (!is_int($limit) || !is_int($offset)) {
            return new JsonResponse(['error' => 'Invalid usage pagination.'], 422);
        }

        try {
            return new JsonResponse(['section' => $this->sections->get($id), ...$this->usage->forSection($id, $limit, $offset)]);
        } catch (\OutOfBoundsException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }
    }

    #[Route('/api/v1/admin/sections/{id}/refresh', name: 'content.sections.refresh', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    public function refresh(string $id, Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);
            if (!$payload instanceof \stdClass || count(get_object_vars($payload)) !== 2
                || !is_int($sectionVersion = $payload->sectionVersion ?? null) || $sectionVersion < 1
                || !is_array($pages = $payload->pages ?? null) || $pages === [] || count($pages) > 50) {
                throw new \InvalidArgumentException('A section version and 1 to 50 page revisions are required.');
            }

            $targets = [];
            $seen = [];
            foreach ($pages as $page) {
                if (!$page instanceof \stdClass || count(get_object_vars($page)) !== 2
                    || !is_string($pageId = $page->pageId ?? null)
                    || !is_int($version = $page->version ?? null) || $version < 1 || isset($seen[$pageId])) {
                    throw new \InvalidArgumentException('Each page must have a unique ID and a positive revision.');
                }
                $targets[] = ['pageId' => PageId::fromString($pageId), 'version' => $version];
                $seen[$pageId] = true;
            }

            return new JsonResponse(['items' => ($this->refresh)($id, $sectionVersion, $targets)]);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        } catch (\OutOfBoundsException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (ReusableSectionConflict $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }
    }
}
