<?php

declare(strict_types=1);

namespace Cms\Schema\Presentation\Http;

use Cms\Schema\Application\ContentTypeView;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentType;
use Cms\Schema\Application\ListContentTypes;
use Cms\Schema\Application\UpdateContentType;
use Cms\Schema\Domain\ContentTypeFieldsConflict;
use Cms\Schema\Domain\ContentTypeKeyAlreadyExists;
use Cms\Schema\Domain\ContentTypeNotFound;
use Cms\Schema\Domain\ContentTypeVersionConflict;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ContentTypeController
{
    public function __construct(private CreateContentType $createType, private GetContentType $getType, private ListContentTypes $listTypes, private UpdateContentType $updateType)
    {
    }

    #[Route('/api/v1/admin/content-types', name: 'schema.content_types.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['items' => array_map(static fn (ContentTypeView $type): array => $type->toArray(), ($this->listTypes)())]);
    }

    #[Route('/api/v1/admin/content-types', name: 'schema.content_types.create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $parsed = $this->payload($request, false);
        if ($parsed instanceof JsonResponse) {
            return $parsed;
        }
        try {
            $type = ($this->createType)($parsed['name'], $parsed['apiName'], $parsed['fields']);
        } catch (ContentTypeKeyAlreadyExists|ContentTypeFieldsConflict $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($type->toArray(), 201);
    }

    #[Route('/api/v1/admin/content-types/{id}', name: 'schema.content_types.get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        try {
            return new JsonResponse(($this->getType)($id)->toArray());
        } catch (ContentTypeNotFound) {
            return new JsonResponse(['error' => 'Content type not found.'], 404);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/content-types/{id}', name: 'schema.content_types.update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $parsed = $this->payload($request, true);
        if ($parsed instanceof JsonResponse) {
            return $parsed;
        }
        try {
            $type = ($this->updateType)($id, $parsed['name'], $parsed['apiName'], $parsed['fields'], $parsed['version']);
        } catch (ContentTypeNotFound) {
            return new JsonResponse(['error' => 'Content type not found.'], 404);
        } catch (ContentTypeKeyAlreadyExists|ContentTypeFieldsConflict|ContentTypeVersionConflict $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($type->toArray());
    }

    /** @return array{name:string,apiName:string,fields:list<array<string,mixed>>,version:int}|JsonResponse */
    private function payload(Request $request, bool $requireVersion): array|JsonResponse
    {
        $payload = $request->toArray();
        $allowed = $requireVersion ? ['name', 'apiName', 'fields', 'version'] : ['name', 'apiName', 'fields'];
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ([] !== $unexpected) {
            return new JsonResponse(['error' => sprintf('Unexpected content type property "%s".', reset($unexpected))], 422);
        }
        $name = $payload['name'] ?? null;
        $apiName = $payload['apiName'] ?? null;
        $fields = $payload['fields'] ?? null;
        $version = $payload['version'] ?? 1;
        if (!is_string($name) || !is_string($apiName) || !is_array($fields) || !array_is_list($fields) || !is_int($version) || ($requireVersion && !isset($payload['version']))) {
            return new JsonResponse(['error' => 'Invalid content type payload.'], 422);
        }
        $normalized = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                return new JsonResponse(['error' => 'Every field must be an object.'], 422);
            }
            /** @var array<string, mixed> $normalizedField */
            $normalizedField = $field;
            $normalized[] = $normalizedField;
        }

        return ['name' => $name, 'apiName' => $apiName, 'fields' => $normalized, 'version' => $version];
    }
}
