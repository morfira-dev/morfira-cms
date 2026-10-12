<?php

declare(strict_types=1);

namespace Cms\Media\Domain;

final readonly class MediaAsset
{
    /** @param list<MediaVariant> $variants */
    private function __construct(
        public MediaAssetId $id,
        public string $originalName,
        public string $mimeType,
        public int $width,
        public int $height,
        public int $bytes,
        public string $defaultAlt,
        public array $variants,
        public \DateTimeImmutable $createdAt,
    ) {
        if ($originalName === '' || mb_strlen($originalName) > 255) {
            throw new \InvalidArgumentException('Original file name must contain between 1 and 255 characters.');
        }

        if ($mimeType !== 'image/webp') {
            throw new \InvalidArgumentException('Stored media assets must be WebP images.');
        }

        if ($width < 1 || $height < 1 || $bytes < 1 || $variants === []) {
            throw new \InvalidArgumentException('Media asset dimensions, byte size and variants must be present.');
        }

        if (mb_strlen($defaultAlt) > 500) {
            throw new \InvalidArgumentException('Default alternative text must not exceed 500 characters.');
        }
    }

    /** @param list<MediaVariant> $variants */
    public static function create(
        MediaAssetId $id,
        string $originalName,
        int $width,
        int $height,
        int $bytes,
        string $defaultAlt,
        array $variants,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            trim(basename($originalName)),
            'image/webp',
            $width,
            $height,
            $bytes,
            trim($defaultAlt),
            $variants,
            $createdAt,
        );
    }

    /** @param list<MediaVariant> $variants */
    public static function reconstitute(
        MediaAssetId $id,
        string $originalName,
        string $mimeType,
        int $width,
        int $height,
        int $bytes,
        string $defaultAlt,
        array $variants,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $originalName, $mimeType, $width, $height, $bytes, $defaultAlt, $variants, $createdAt);
    }
}
