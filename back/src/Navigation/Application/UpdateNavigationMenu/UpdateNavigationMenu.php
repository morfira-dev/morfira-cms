<?php

declare(strict_types=1);

namespace Cms\Navigation\Application\UpdateNavigationMenu;

use Cms\Navigation\Application\NavigationMenuView;
use Cms\Navigation\Domain\NavigationCode;
use Cms\Navigation\Domain\NavigationItem;
use Cms\Navigation\Domain\NavigationMenuId;
use Cms\Navigation\Domain\NavigationMenuNotFound;
use Cms\Navigation\Domain\NavigationMenuRepository;
use Cms\Navigation\Domain\NavigationVersionConflict;

final readonly class UpdateNavigationMenu
{
    public function __construct(private NavigationMenuRepository $menus) {}
    /** @param list<array<string, mixed>> $items */
    public function __invoke(string $id, string $name, string $code, array $items, int $version): NavigationMenuView
    {
        $menu = $this->menus->get(NavigationMenuId::fromString($id));
        if ($menu === null) { throw new NavigationMenuNotFound(); }
        if ($menu->version !== $version) { throw new NavigationVersionConflict(); }
        $revised = $menu->revise($name, new NavigationCode($code), NavigationItem::listFromArray($items), new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->menus->save($revised, $version);
        return NavigationMenuView::fromMenu($revised);
    }
}
