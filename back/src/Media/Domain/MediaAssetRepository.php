<?php

declare(strict_types=1);

namespace Cms\Media\Domain;

interface MediaAssetRepository
{
    public function add(MediaAsset $asset): void;

    public function get(MediaAssetId $id): ?MediaAsset;

    /** @return list<MediaAsset> */
    public function all(): array;
}
