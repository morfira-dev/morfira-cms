<?php

declare(strict_types=1);

namespace Cms\Media\Application;

use Cms\Media\Domain\MediaAssetId;

interface ImageProcessor
{
    public function process(string $sourcePath, MediaAssetId $assetId): ProcessedImage;

    public function remove(MediaAssetId $assetId): void;
}
