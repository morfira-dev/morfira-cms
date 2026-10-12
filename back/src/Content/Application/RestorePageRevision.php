<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageRevisionNotFound;

final readonly class RestorePageRevision
{
    public function __construct(private PageRepository $pages)
    {
    }

    public function __invoke(string $pageId, int $revisionNumber, int $expectedVersion): PageView
    {
        $id = PageId::fromString($pageId);
        $page = $this->pages->get($id);
        if ($page === null) {
            throw new PageNotFound();
        }
        $revision = $this->pages->revision($id, $revisionNumber);
        if ($revision === null) {
            throw new PageRevisionNotFound();
        }

        $restored = $page->revise($revision->title, $revision->slug, $revision->blocks, $revision->seo, new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $revision->resolvedLayout(), $revision->locale, $revision->resolvedTranslationGroupId());
        $this->pages->save($restored, $expectedVersion);

        return PageView::fromPage($restored);
    }
}
