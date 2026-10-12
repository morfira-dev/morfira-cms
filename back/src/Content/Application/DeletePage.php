<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;

final readonly class DeletePage
{
    public function __construct(private PageRepository $pages, private ContentDeletionGateway $deletion)
    {
    }

    public function __invoke(string $pageId): void
    {
        $id = PageId::fromString($pageId);
        if ($this->pages->get($id) === null) {
            throw new PageNotFound();
        }

        $this->deletion->deletePage($id->value);
    }
}
