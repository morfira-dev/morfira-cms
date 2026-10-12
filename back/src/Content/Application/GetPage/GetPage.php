<?php

declare(strict_types=1);

namespace Cms\Content\Application\GetPage;

use Cms\Content\Application\PageView;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;

final readonly class GetPage
{
    public function __construct(private PageRepository $pages)
    {
    }

    public function __invoke(string $id): PageView
    {
        $page = $this->pages->get(PageId::fromString($id));

        if ($page === null) {
            throw new PageNotFound();
        }

        return PageView::fromPage($page);
    }
}
