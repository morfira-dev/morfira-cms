<?php

declare(strict_types=1);

namespace Cms\Media\Application;

use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaVariant;

final readonly class MediaAssetView
{
    /** @param list<array{width: int, height: int, url: string}> $variants */
    public function __construct(
        public string $id,
        public string $originalName,
        public string $mimeType,
        public int $width,
        public int $height,
        public int $size,
        public string $defaultAlt,
        public ?string $collection,
        /** @var list<string> */
        public array $tags,
        public array $variants,
        public string $createdAt,
    ) {
    }

    public static function fromAsset(MediaAsset $asset): self
    {
        return new self(
            $asset->id->value,
            $asset->originalName,
            $asset->mimeType,
            $asset->width,
            $asset->height,
            $asset->bytes,
            $asset->defaultAlt,
            $asset->collection,
            $asset->tags,
            array_map(static fn (MediaVariant $variant): array => [
                'url' => $variant->url,
                'width' => $variant->width,
                'height' => $variant->height,
            ], $asset->variants),
            $asset->createdAt->format(DATE_ATOM),
        );
    }

    /** @return array{id: string, originalName: string, mimeType: string, size: int, width: int, height: int, defaultAlt: string, collection: ?string, tags: list<string>, variants: list<array{url: string, width: int, height: int}>, createdAt: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'originalName' => $this->originalName,
            'mimeType' => $this->mimeType,
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->size,
            'defaultAlt' => $this->defaultAlt,
            'collection' => $this->collection,
            'tags' => $this->tags,
            'variants' => $this->variants,
            'createdAt' => $this->createdAt,
        ];
    }
}
