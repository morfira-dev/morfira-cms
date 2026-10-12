<?php

declare(strict_types=1);

namespace Cms\Navigation\Application\PublishNavigationMenu;

use Cms\Navigation\Application\PublishedNavigationView;
use Cms\Navigation\Domain\NavigationItem;
use Cms\Navigation\Domain\NavigationMenuId;
use Cms\Navigation\Domain\NavigationMenuNotFound;
use Cms\Navigation\Domain\NavigationMenuRepository;
use Cms\Navigation\Domain\NavigationVersionConflict;
use Cms\Navigation\Domain\PublishedNavigationMenu;
use Cms\Navigation\Domain\PublishedNavigationRepository;

final readonly class PublishNavigationMenu
{
    public function __construct(private NavigationMenuRepository $drafts, private PublishedNavigationRepository $published) {}
    public function __invoke(string $id, int $version): PublishedNavigationView
    {
        $menu = $this->drafts->get(NavigationMenuId::fromString($id));
        if ($menu === null) { throw new NavigationMenuNotFound(); }
        if ($menu->version !== $version) { throw new NavigationVersionConflict(); }
        $snapshot = new PublishedNavigationMenu(
            NavigationMenuId::new()->value,
            $menu->id->value,
            $menu->version,
            $menu->name,
            $menu->code->value,
            array_map(static fn (NavigationItem $item): array => $item->toArray(), $menu->items),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        return PublishedNavigationView::fromMenu($this->published->publish($snapshot));
    }
}
