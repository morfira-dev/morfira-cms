<?php

declare(strict_types=1);

namespace Cms\Media\Presentation\Http;

use Cms\Media\Application\ListMedia\ListMedia;
use Cms\Media\Application\MediaAssetView;
use Cms\Media\Application\MediaTooLarge;
use Cms\Media\Application\DeleteMedia;
use Cms\Media\Application\UpdateMediaMetadata;
use Cms\Media\Application\UnsupportedMediaType;
use Cms\Media\Application\UploadImage\UploadImage;
use Cms\Media\Domain\MediaAssetInUse;
use Cms\Media\Domain\MediaAssetNotFound;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MediaController
{
    public function __construct(
        private UploadImage $uploadImage,
        private ListMedia $listMedia,
        private UpdateMediaMetadata $updateMediaMetadata,
        private DeleteMedia $deleteMedia,
    ) {
    }

    #[Route('/api/v1/admin/media', name: 'media.assets.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map(
                static fn (MediaAssetView $asset): array => $asset->toArray(),
                ($this->listMedia)(),
            ),
        ]);
    }

    #[Route('/api/v1/admin/media', name: 'media.assets.upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $defaultAlt = $request->request->get('alt', '');

        if ($file instanceof UploadedFile && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return new JsonResponse(['error' => 'Image must be between 1 byte and 10 MB.'], 413);
        }

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(['error' => 'A valid image file is required.'], 422);
        }

        if (!is_string($defaultAlt)) {
            return new JsonResponse(['error' => 'Field "alt" must be a string.'], 422);
        }

        try {
            $asset = ($this->uploadImage)($file->getPathname(), $file->getClientOriginalName(), $defaultAlt);
        } catch (MediaTooLarge $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 413);
        } catch (UnsupportedMediaType $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 415);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new JsonResponse($asset->toArray(), 201);
    }

    #[Route('/api/v1/admin/media/{id}', name: 'media.assets.update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $keys = array_keys($payload);
        sort($keys);
        if ($keys !== ['collection', 'defaultAlt', 'tags']
            || !is_string($payload['defaultAlt'])
            || ($payload['collection'] !== null && !is_string($payload['collection']))
            || !is_array($payload['tags'])
            || !array_is_list($payload['tags'])
            || array_filter($payload['tags'], static fn (mixed $tag): bool => !is_string($tag)) !== []) {
            return new JsonResponse(['error' => 'Invalid media metadata payload.'], 422);
        }

        try {
            /** @var list<string> $tags */
            $tags = $payload['tags'];

            return new JsonResponse(($this->updateMediaMetadata)($id, $payload['defaultAlt'], $payload['collection'], $tags)->toArray());
        } catch (MediaAssetNotFound) {
            return new JsonResponse(['error' => 'Media asset not found.'], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/media/{id}', name: 'media.assets.delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            ($this->deleteMedia)($id);
        } catch (MediaAssetNotFound) {
            return new JsonResponse(['error' => 'Media asset not found.'], 404);
        } catch (MediaAssetInUse $exception) {
            return new JsonResponse(['error' => $exception->getMessage(), 'usages' => $exception->usages], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new JsonResponse(null, 204);
    }
}
