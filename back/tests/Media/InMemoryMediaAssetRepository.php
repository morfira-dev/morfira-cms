<?php

declare(strict_types=1);

namespace Cms\Tests\Media;

use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaAssetRepository;

final class InMemoryMediaAssetRepository implements MediaAssetRepository
{
    /** @var array<string, MediaAsset> */
    public array $items = [];

    public bool $failOnAdd = false;

    public function add(MediaAsset $asset): void
    {
        if ($this->failOnAdd) {
            throw new \RuntimeException('Persistence failed.');
        }

        $this->items[$asset->id->value] = $asset;
    }

    public function get(MediaAssetId $id): ?MediaAsset
    {
        return $this->items[$id->value] ?? null;
    }

    public function save(MediaAsset $asset): void
    {
        $this->items[$asset->id->value] = $asset;
    }

    public function remove(MediaAssetId $id): void
    {
        unset($this->items[$id->value]);
    }

    public function all(): array
    {
        return array_values($this->items);
    }
}
