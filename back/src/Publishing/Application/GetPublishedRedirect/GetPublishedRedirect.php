<?php

declare(strict_types=1);

namespace Cms\Publishing\Application\GetPublishedRedirect;

use Cms\Publishing\Application\PublishedPageView;
use Cms\Publishing\Domain\PublishedPageRepository;

final readonly class GetPublishedRedirect
{
    public function __construct(private PublishedPageRepository $pages)
    {
    }

    public function __invoke(string $sourceSlug): ?PublishedPageView
    {
        $page = $this->pages->findRedirectTargetBySlug($sourceSlug);

        return $page === null ? null : PublishedPageView::fromPublishedPage($page);
    }
}
