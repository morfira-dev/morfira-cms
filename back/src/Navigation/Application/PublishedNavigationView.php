<?php

declare(strict_types=1);

namespace Cms\Navigation\Application;

use Cms\Navigation\Domain\PublishedNavigationMenu;

final readonly class PublishedNavigationView
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public string $snapshotId,
        public string $navigationId,
        public int $sourceVersion,
        public string $name,
        public string $code,
        public array $items,
        public string $publishedAt,
    ) {
    }

    public static function fromMenu(PublishedNavigationMenu $menu): self
    {
        return new self($menu->snapshotId, $menu->menuId, $menu->sourceVersion, $menu->name, $menu->code, $menu->items, $menu->publishedAt->format(DATE_ATOM));
    }

    /** @return array{snapshotId: string, navigationId: string, sourceVersion: int, name: string, code: string, items: list<array<string, mixed>>, publishedAt: string} */
    public function toArray(): array
    {
        return ['snapshotId' => $this->snapshotId, 'navigationId' => $this->navigationId, 'sourceVersion' => $this->sourceVersion, 'name' => $this->name, 'code' => $this->code, 'items' => $this->items, 'publishedAt' => $this->publishedAt];
    }
}
