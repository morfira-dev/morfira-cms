<?php

declare(strict_types=1);

namespace Cms\Tests\Navigation;

use Cms\Navigation\Domain\NavigationCodeAlreadyExists;
use Cms\Navigation\Domain\NavigationMenu;
use Cms\Navigation\Domain\NavigationMenuId;
use Cms\Navigation\Domain\NavigationMenuRepository;
use Cms\Navigation\Domain\NavigationVersionConflict;

final class InMemoryNavigationMenuRepository implements NavigationMenuRepository
{
    /** @var array<string, NavigationMenu> */
    private array $menus = [];

    public function add(NavigationMenu $menu): void
    {
        $this->assertCodeAvailable($menu);
        $this->menus[$menu->id->value] = $menu;
    }

    public function get(NavigationMenuId $id): ?NavigationMenu { return $this->menus[$id->value] ?? null; }

    public function save(NavigationMenu $menu, int $expectedVersion): void
    {
        $current = $this->menus[$menu->id->value] ?? null;
        if ($current === null || $current->version !== $expectedVersion) { throw new NavigationVersionConflict(); }
        $this->assertCodeAvailable($menu);
        $this->menus[$menu->id->value] = $menu;
    }

    public function all(): array { return array_values($this->menus); }

    private function assertCodeAvailable(NavigationMenu $menu): void
    {
        foreach ($this->menus as $existing) {
            if ($existing->id->value !== $menu->id->value && $existing->code->value === $menu->code->value) {
                throw new NavigationCodeAlreadyExists($menu->code->value);
            }
        }
    }
}
