<?php

declare(strict_types=1);

namespace Cms\Tests\Content;

use Cms\Content\Domain\Entry;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntryVersionConflict;

final class InMemoryEntryRepository implements EntryRepository
{
    /** @var array<string, Entry> */ private array $entries = [];

    public function add(Entry $entry): void
    {
        $this->entries[$entry->id->value] = $entry;
    }

    public function get(EntryId $id): ?Entry
    {
        return $this->entries[$id->value] ?? null;
    }

    public function save(Entry $entry, int $expectedVersion): void
    {
        $current = $this->entries[$entry->id->value] ?? null;
        if (null === $current || $current->version !== $expectedVersion) {
            throw new EntryVersionConflict();
        }
        $this->entries[$entry->id->value] = $entry;
    }

    public function all(?string $contentTypeId = null): array
    {
        return array_values(array_filter($this->entries, static fn (Entry $entry): bool => null === $contentTypeId || $entry->contentTypeId === $contentTypeId));
    }
}
