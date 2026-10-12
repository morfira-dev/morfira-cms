<?php

declare(strict_types=1);

namespace Cms\Tests\Content;

use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageSlugAlreadyExists;

final class InMemoryPageRepository implements PageRepository
{
    /** @var array<string, Page> */
    private array $pages = [];

    public function add(Page $page): void
    {
        foreach ($this->pages as $storedPage) {
            if ($storedPage->slug->value === $page->slug->value) {
                throw new PageSlugAlreadyExists($page->slug);
            }
        }

        $this->pages[$page->id->value] = $page;
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
        $this->pages[$page->id->value] = $page;
    }
}
