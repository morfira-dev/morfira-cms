<?php

declare(strict_types=1);

namespace Cms\Navigation\Application\GetPublishedNavigation;

use Cms\Navigation\Application\PublishedNavigationView;
use Cms\Navigation\Domain\NavigationCode;
use Cms\Navigation\Domain\PublishedNavigationRepository;

final readonly class GetPublishedNavigation
{
    public function __construct(private PublishedNavigationRepository $published) {}
    public function __invoke(string $code): ?PublishedNavigationView
    {
        $menu = $this->published->findByCode((new NavigationCode($code))->value);
        return $menu === null ? null : PublishedNavigationView::fromMenu($menu);
    }
}
