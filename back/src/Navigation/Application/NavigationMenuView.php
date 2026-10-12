<?php

declare(strict_types=1);

namespace Cms\Navigation\Application;

use Cms\Navigation\Domain\NavigationItem;
use Cms\Navigation\Domain\NavigationMenu;

final readonly class NavigationMenuView
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public array $items,
        public int $version,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromMenu(NavigationMenu $menu): self
    {
        return new self(
            $menu->id->value,
            $menu->name,
            $menu->code->value,
            array_map(static fn (NavigationItem $item): array => $item->toArray(), $menu->items),
            $menu->version,
            $menu->createdAt->format(DATE_ATOM),
            $menu->updatedAt->format(DATE_ATOM),
        );
    }

    /** @return array{id: string, name: string, code: string, items: list<array<string, mixed>>, version: int, createdAt: string, updatedAt: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'items' => $this->items, 'version' => $this->version, 'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
