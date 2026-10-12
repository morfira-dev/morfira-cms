<?php

declare(strict_types=1);

namespace Cms\Content\Application\UpdatePage;

use Cms\Content\Application\PageView;
use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Domain\PageSlug;
use Cms\Content\Domain\PageTitle;

final readonly class UpdatePage
{
    public function __construct(private PageRepository $pages)
    {
    }

    /** @param list<array<string, mixed>> $blocks */
    public function __invoke(string $id, string $title, string $slug, array $blocks, PageSeo $seo, int $version): PageView
    {
        $page = $this->pages->get(PageId::fromString($id));

        if ($page === null) {
            throw new PageNotFound();
        }

        if ($page->version !== $version) {
            throw new \Cms\Content\Domain\PageVersionConflict();
        }

        $revised = $page->revise(
            new PageTitle($title),
            new PageSlug($slug),
            array_map(PageBlock::fromArray(...), $blocks),
            $seo,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        $this->pages->save($revised, $version);

        return PageView::fromPage($revised);
    }
}
