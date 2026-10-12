<?php

declare(strict_types=1);

namespace Cms\Media\Application\ListMedia;

use Cms\Media\Application\MediaAssetView;
use Cms\Media\Domain\MediaAssetRepository;

final readonly class ListMedia
{
    public function __construct(private MediaAssetRepository $assets)
    {
    }

    /** @return list<MediaAssetView> */
    public function __invoke(): array
    {
        return array_map(MediaAssetView::fromAsset(...), $this->assets->all());
    }
}
