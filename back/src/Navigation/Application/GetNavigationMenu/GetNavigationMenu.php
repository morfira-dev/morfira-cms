<?php

declare(strict_types=1);

namespace Cms\Navigation\Application\GetNavigationMenu;

use Cms\Navigation\Application\NavigationMenuView;
use Cms\Navigation\Domain\NavigationMenuId;
use Cms\Navigation\Domain\NavigationMenuNotFound;
use Cms\Navigation\Domain\NavigationMenuRepository;

final readonly class GetNavigationMenu
{
    public function __construct(private NavigationMenuRepository $menus) {}
    public function __invoke(string $id): NavigationMenuView
    {
        $menu = $this->menus->get(NavigationMenuId::fromString($id));
        if ($menu === null) { throw new NavigationMenuNotFound(); }
        return NavigationMenuView::fromMenu($menu);
    }
}
