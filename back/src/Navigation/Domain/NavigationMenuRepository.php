<?php

declare(strict_types=1);

namespace Cms\Navigation\Domain;

interface NavigationMenuRepository
{
    public function add(NavigationMenu $menu): void;
    public function get(NavigationMenuId $id): ?NavigationMenu;
    public function save(NavigationMenu $menu, int $expectedVersion): void;
    /** @return list<NavigationMenu> */
    public function all(): array;
}
