<?php

declare(strict_types=1);

namespace Cms\Tests\Content;

use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageSlugAlreadyExists;
use Cms\Content\Domain\PageRevision;
use Cms\Content\Domain\PageVersionConflict;

final class InMemoryPageRepository implements PageRepository
{
    /** @var array<string, Page> */
    private array $pages = [];

    /** @var array<string, array<int, PageRevision>> */
    private array $revisions = [];

    public function add(Page $page): void
    {
        foreach ($this->pages as $storedPage) {
            if ($storedPage->slug->value === $page->slug->value) {
                throw new PageSlugAlreadyExists($page->slug);
            }
        }

        $this->pages[$page->id->value] = $page;
        $this->record($page);
    }

    public function all(): array
    {
        return array_values($this->pages);
    }

    public function get(PageId $id): ?Page
    {
        return $this->pages[$id->value] ?? null;
    }

    public function save(Page $page, int $expectedVersion): void
    {
        $current = $this->pages[$page->id->value] ?? null;
        if ($current === null || $current->version !== $expectedVersion) {
            throw new PageVersionConflict();
        }
        $this->pages[$page->id->value] = $page;
        $this->record($page);
    }

    public function revisions(PageId $id): array
    {
        $revisions = array_values($this->revisions[$id->value] ?? []);
        usort($revisions, static fn (PageRevision $left, PageRevision $right): int => $right->revision <=> $left->revision);
        return $revisions;
    }

    public function revision(PageId $id, int $revision): ?PageRevision
    {
        return $this->revisions[$id->value][$revision] ?? null;
    }

    private function record(Page $page): void
    {
        $this->revisions[$page->id->value][$page->version] = new PageRevision($page->version, $page->title, $page->slug, $page->blocks, $page->seo, $page->updatedAt);
    }
}
