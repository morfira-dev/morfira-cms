<?php

declare(strict_types=1);

namespace Cms\Media\Application;

use Cms\Media\Domain\MediaAssetId;

interface PrivateImageProcessor extends ImageProcessor
{
    public function variantPath(MediaAssetId $id, string $filename): ?string;
}
