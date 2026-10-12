<?php

declare(strict_types=1);

namespace Cms\Media\Presentation\Http;

use Cms\Media\Application\ListMedia\ListMedia;
use Cms\Media\Application\MediaAssetView;
use Cms\Media\Application\MediaTooLarge;
use Cms\Media\Application\UnsupportedMediaType;
use Cms\Media\Application\UploadImage\UploadImage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MediaController
{
    public function __construct(
        private UploadImage $uploadImage,
        private ListMedia $listMedia,
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
}
