<?php

declare(strict_types=1);

namespace Cms\Navigation\Domain;

interface PublishedNavigationRepository
{
    public function publish(PublishedNavigationMenu $menu): PublishedNavigationMenu;
    public function findByCode(string $code): ?PublishedNavigationMenu;
}
