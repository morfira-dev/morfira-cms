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

        if (preg_match('#\A(?:/media/[0-9a-f-]+|/api/v1/admin/media/[0-9a-f-]+/variants)/[0-9]+w\.webp\z#', $url) !== 1) {
            throw new \InvalidArgumentException('Media variant URL must be an immutable local WebP URL.');
        }
    }

    public static function urlFor(MediaAssetId $id, int $width, bool $isPrivate): string
    {
        return sprintf($isPrivate ? '/api/v1/admin/media/%s/variants/%dw.webp' : '/media/%s/%dw.webp', $id->value, $width);
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
