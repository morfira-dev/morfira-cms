<?php

declare(strict_types=1);

namespace Cms\Media\Application;

use Cms\Content\Application\ContentMediaUsage;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaAssetInUse;
use Cms\Media\Domain\MediaAssetNotFound;
use Cms\Media\Domain\MediaAssetRepository;
use Cms\Publishing\Application\PublishedMediaUsage;

final readonly class DeleteMedia
{
    public function __construct(
        private MediaAssetRepository $assets,
        private ImageProcessor $images,
        private ContentMediaUsage $contentUsage,
        private PublishedMediaUsage $publishedUsage,
        private ?PrivateImageProcessor $privateImages = null,
    ) {
    }

    public function __invoke(string $id): void
    {
        $assetId = MediaAssetId::fromString($id);
        $asset = $this->assets->get($assetId);
        if ($asset === null) {
            throw new MediaAssetNotFound();
        }
        $usages = [...$this->contentUsage->references($id), ...$this->publishedUsage->references($id)];
        if ($usages !== []) {
            throw new MediaAssetInUse(array_values(array_unique($usages)));
        }

        $processor = $asset->isPrivate
            ? ($this->privateImages ?? throw new \LogicException('Private media storage is not configured.'))
            : $this->images;
        $this->assets->remove($assetId);
        $processor->remove($assetId);
    }
}
