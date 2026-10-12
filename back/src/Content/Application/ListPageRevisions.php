<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;

final readonly class ListPageRevisions
{
    public function __construct(private PageRepository $pages)
    {
    }

    /** @return list<PageRevisionView> */
    public function __invoke(string $pageId): array
    {
        $id = PageId::fromString($pageId);
        if ($this->pages->get($id) === null) {
            throw new PageNotFound();
        }

        return array_map(PageRevisionView::fromRevision(...), $this->pages->revisions($id));
    }
}
