<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\ManageReusableSections;
use Cms\Content\Domain\ReusableSectionConflict;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ReusableSectionController
{
    public function __construct(private ManageReusableSections $sections)
    {
    }

    #[Route('/api/v1/admin/sections', name: 'content.sections.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['items' => $this->sections->all()]);
    }

    #[Route('/api/v1/admin/sections', name: 'content.sections.create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->write($request, null);
    }

    #[Route('/api/v1/admin/sections/{id}', name: 'content.sections.update', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        return $this->write($request, $id);
    }

    #[Route('/api/v1/admin/sections/{id}', name: 'content.sections.delete', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        try {
            $this->sections->delete($id);
            return new Response(status: 204);
        } catch (\OutOfBoundsException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }
    }

    private function write(Request $request, ?string $id): JsonResponse
    {
        $payload = $request->toArray();
        $allowed = $id === null ? ['name', 'blocks'] : ['name', 'blocks', 'version'];
        $name = $payload['name'] ?? null;
        $blocks = $payload['blocks'] ?? null;
        $version = $payload['version'] ?? null;
        if (array_diff(array_keys($payload), $allowed) !== [] || !is_string($name) || !is_array($blocks) || !array_is_list($blocks) || ($id !== null && !is_int($version))) {
            return new JsonResponse(['error' => 'Name, blocks and the expected version are required.'], 422);
        }
        try {
            /** @var list<array<string, mixed>> $blocks */
            $result = $id === null ? $this->sections->create($name, $blocks) : $this->sections->update($id, $name, $blocks, $version);
            return new JsonResponse($result, $id === null ? 201 : 200);
        } catch (ReusableSectionConflict $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\OutOfBoundsException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }
}
