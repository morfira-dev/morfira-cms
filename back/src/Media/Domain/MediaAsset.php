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
        public ?string $collection,
        /** @var list<string> */
        public array $tags,
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

        if ($collection !== null && ($collection === '' || mb_strlen($collection) > 120 || $collection !== trim($collection))) {
            throw new \InvalidArgumentException('Media collection must contain between 1 and 120 trimmed characters.');
        }
        if (count($tags) > 20 || count(array_unique($tags)) !== count($tags)) {
            throw new \InvalidArgumentException('Media tags must be a unique list containing at most 20 values.');
        }
        foreach ($tags as $tag) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $tag) !== 1 || mb_strlen($tag) > 50) {
                throw new \InvalidArgumentException('Media tags must be lowercase slugs of at most 50 characters.');
            }
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
            null,
            [],
            $variants,
            $createdAt,
        );
    }

    /** @param list<string> $tags
     *  @param list<MediaVariant> $variants
     */
    public static function reconstitute(
        MediaAssetId $id,
        string $originalName,
        string $mimeType,
        int $width,
        int $height,
        int $bytes,
        string $defaultAlt,
        ?string $collection,
        array $tags,
        array $variants,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $originalName, $mimeType, $width, $height, $bytes, $defaultAlt, $collection, $tags, $variants, $createdAt);
    }

    /** @param list<string> $tags */
    public function withMetadata(string $defaultAlt, ?string $collection, array $tags): self
    {
        $normalizedTags = array_values(array_unique(array_map(static fn (string $tag): string => mb_strtolower(trim($tag)), $tags)));
        sort($normalizedTags);

        return new self(
            $this->id,
            $this->originalName,
            $this->mimeType,
            $this->width,
            $this->height,
            $this->bytes,
            trim($defaultAlt),
            $collection === null || trim($collection) === '' ? null : trim($collection),
            $normalizedTags,
            $this->variants,
            $this->createdAt,
        );
    }
}
