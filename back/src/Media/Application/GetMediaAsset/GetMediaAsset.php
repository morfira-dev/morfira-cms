<?php

declare(strict_types=1);

namespace Cms\Media\Application\GetMediaAsset;

use Cms\Media\Application\MediaAssetView;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaAssetRepository;

final readonly class GetMediaAsset
{
    public function __construct(private MediaAssetRepository $assets)
    {
    }

    public function __invoke(string $id): ?MediaAssetView
    {
        $asset = $this->assets->get(MediaAssetId::fromString($id));

        return $asset === null ? null : MediaAssetView::fromAsset($asset);
    }
}
