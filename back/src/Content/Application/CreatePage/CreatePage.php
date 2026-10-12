<?php

declare(strict_types=1);

namespace Cms\Content\Application\CreatePage;

use Cms\Content\Application\PageView;
use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageSlug;
use Cms\Content\Domain\PageTitle;

final readonly class CreatePage
{
    public function __construct(private PageRepository $pages)
    {
    }

    public function __invoke(string $title, string $slug): PageView
    {
        $page = Page::draft(
            PageId::new(),
            new PageTitle($title),
            new PageSlug($slug),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        $this->pages->add($page);

        return PageView::fromPage($page);
    }
}
