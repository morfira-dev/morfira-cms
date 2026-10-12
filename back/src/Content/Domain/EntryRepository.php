<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

interface EntryRepository
{
    public function add(Entry $entry): void;

    public function get(EntryId $id): ?Entry;

    public function save(Entry $entry, int $expectedVersion): void;

    /** @return list<Entry> */
    public function all(?string $contentTypeId = null): array;
}
