<?php

declare(strict_types=1);

namespace Cms\Media\Application;

use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaAssetNotFound;
use Cms\Media\Domain\MediaAssetRepository;

final readonly class UpdateMediaMetadata
{
    public function __construct(private MediaAssetRepository $assets)
    {
    }

    /** @param list<string> $tags */
    public function __invoke(string $id, string $defaultAlt, ?string $collection, array $tags): MediaAssetView
    {
        $asset = $this->assets->get(MediaAssetId::fromString($id));
        if ($asset === null) {
            throw new MediaAssetNotFound();
        }
        $updated = $asset->withMetadata($defaultAlt, $collection, $tags);
        $this->assets->save($updated);

        return MediaAssetView::fromAsset($updated);
    }
}
