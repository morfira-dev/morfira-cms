<?php

declare(strict_types=1);

namespace Cms\Media\Domain;

final readonly class MediaVariant
{
    public function __construct(
        public int $width,
        public int $height,
        public int $bytes,
        public string $url,
    ) {
        if ($width < 1 || $height < 1 || $bytes < 1) {
            throw new \InvalidArgumentException('Media variant dimensions and byte size must be positive.');
        }

        if (preg_match('#^/media/[0-9a-f-]+/[0-9]+w\.webp$#', $url) !== 1) {
            throw new \InvalidArgumentException('Media variant URL must be an immutable local WebP URL.');
        }
    }

    /** @return array{width: int, height: int, bytes: int, url: string} */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'bytes' => $this->bytes,
            'url' => $this->url,
        ];
    }
}
