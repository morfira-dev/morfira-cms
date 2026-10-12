<?php

declare(strict_types=1);

namespace Cms\Tests\Navigation;

use Cms\Navigation\Domain\NavigationCodeAlreadyExists;
use Cms\Navigation\Domain\PublishedNavigationMenu;
use Cms\Navigation\Domain\PublishedNavigationRepository;

final class InMemoryPublishedNavigationRepository implements PublishedNavigationRepository
{
    /** @var array<string, PublishedNavigationMenu> */
    private array $snapshots = [];
    /** @var array<string, string> */
    private array $routes = [];

    public function publish(PublishedNavigationMenu $menu): PublishedNavigationMenu
    {
        $key = $menu->menuId . ':' . $menu->sourceVersion;
        $snapshot = $this->snapshots[$key] ?? $menu;
        $owner = $this->routes[$snapshot->code] ?? null;
        if ($owner !== null && !str_starts_with($owner, $snapshot->menuId . ':')) { throw new NavigationCodeAlreadyExists($snapshot->code); }
        $this->snapshots[$key] = $snapshot;
        foreach ($this->routes as $code => $activeKey) {
            if (str_starts_with($activeKey, $snapshot->menuId . ':')) { unset($this->routes[$code]); }
        }
        $this->routes[$snapshot->code] = $key;
        return $snapshot;
    }

    public function findByCode(string $code): ?PublishedNavigationMenu
    {
        $key = $this->routes[$code] ?? null;
        return $key === null ? null : ($this->snapshots[$key] ?? null);
    }
}
