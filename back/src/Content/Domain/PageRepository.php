<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

interface PageRepository
{
    public function add(Page $page): void;

    public function get(PageId $id): ?Page;

    public function save(Page $page, int $expectedVersion): void;

    /** @return list<PageRevision> */
    public function revisions(PageId $id): array;

    public function revision(PageId $id, int $revision): ?PageRevision;

    /** @return list<Page> */
    public function all(): array;
}
