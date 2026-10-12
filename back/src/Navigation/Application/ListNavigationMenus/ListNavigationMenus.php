<?php

declare(strict_types=1);

namespace Cms\Navigation\Application\ListNavigationMenus;

use Cms\Navigation\Application\NavigationMenuView;
use Cms\Navigation\Domain\NavigationMenuRepository;

final readonly class ListNavigationMenus
{
    public function __construct(private NavigationMenuRepository $menus) {}
    /** @return list<NavigationMenuView> */
    public function __invoke(): array { return array_map(NavigationMenuView::fromMenu(...), $this->menus->all()); }
}
