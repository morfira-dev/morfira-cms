<?php

declare(strict_types=1);

namespace Cms\Publishing\Application\ListPublishedPages;

use Cms\Publishing\Application\PublishedPageView;
use Cms\Publishing\Domain\PublishedPage;
use Cms\Publishing\Domain\PublishedPageRepository;

final readonly class ListPublishedPages
{
    public function __construct(private PublishedPageRepository $pages)
    {
    }

    /** @return list<PublishedPageView> */
    public function __invoke(): array
    {
        return array_map(
            PublishedPageView::fromPublishedPage(...),
            array_values(array_filter(
                $this->pages->allActive(),
                static fn (PublishedPage $page): bool => $page->slug !== '404' && !$page->seo['noIndex'],
            )),
        );
    }
}
