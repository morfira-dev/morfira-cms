<?php

declare(strict_types=1);

namespace Cms\Media\Application;

use Cms\Media\Domain\MediaVariant;

final readonly class ProcessedImage
{
    /** @param list<MediaVariant> $variants */
    public function __construct(
        public int $width,
        public int $height,
        public int $bytes,
        public array $variants,
    ) {
    }
}
