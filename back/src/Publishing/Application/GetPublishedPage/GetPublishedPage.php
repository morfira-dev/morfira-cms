<?php

declare(strict_types=1);

namespace Cms\Publishing\Application\GetPublishedPage;

use Cms\Publishing\Application\PublishedPageView;
use Cms\Publishing\Domain\PublishedPageRepository;

final readonly class GetPublishedPage
{
    public function __construct(private PublishedPageRepository $pages)
    {
    }

    public function __invoke(string $slug): ?PublishedPageView
    {
        $page = $this->pages->findBySlug($slug);

        return $page === null ? null : PublishedPageView::fromPublishedPage($page);
    }
}
