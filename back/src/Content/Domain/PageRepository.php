<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

interface PageRepository
{
    public function add(Page $page): void;

    public function get(PageId $id): ?Page;

    public function save(Page $page, int $expectedVersion): void;

    /** @return list<Page> */
    public function all(): array;
}
