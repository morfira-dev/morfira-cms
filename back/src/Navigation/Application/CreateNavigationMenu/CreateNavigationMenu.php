<?php

declare(strict_types=1);

namespace Cms\Navigation\Application\CreateNavigationMenu;

use Cms\Navigation\Application\NavigationMenuView;
use Cms\Navigation\Domain\NavigationCode;
use Cms\Navigation\Domain\NavigationMenu;
use Cms\Navigation\Domain\NavigationMenuId;
use Cms\Navigation\Domain\NavigationMenuRepository;
use Cms\Navigation\Domain\NavigationItem;

final readonly class CreateNavigationMenu
{
    public function __construct(private NavigationMenuRepository $menus) {}

    /** @param list<array<string, mixed>> $items */
    public function __invoke(string $name, string $code, array $items): NavigationMenuView
    {
        $menu = NavigationMenu::draft(NavigationMenuId::new(), $name, new NavigationCode($code), NavigationItem::listFromArray($items), new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->menus->add($menu);

        return NavigationMenuView::fromMenu($menu);
    }
}
