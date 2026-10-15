<?php

declare(strict_types=1);

namespace Cms\Tests\Content;

use Cms\Content\Domain\Entry;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntryRevision;
use Cms\Content\Domain\EntryVersionConflict;

final class InMemoryEntryRepository implements EntryRepository
{
    /** @var array<string, Entry> */ private array $entries = [];
    /** @var array<string, list<EntryRevision>> */ private array $revisions = [];

    public function add(Entry $entry): void
    {
        $this->entries[$entry->id->value] = $entry;
        $this->revisions[$entry->id->value] = [$this->toRevision($entry)];
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
        $this->revisions[$entry->id->value][] = $this->toRevision($entry);
    }

    public function revisions(EntryId $id): array
    {
        return array_reverse($this->revisions[$id->value] ?? []);
    }

    public function revision(EntryId $id, int $revision): ?EntryRevision
    {
        foreach ($this->revisions[$id->value] ?? [] as $candidate) {
            if ($candidate->revision === $revision) {
                return $candidate;
            }
        }

        return null;
    }

    public function all(?string $contentTypeId = null): array
    {
        return array_values(array_filter($this->entries, static fn (Entry $entry): bool => null === $contentTypeId || $entry->contentTypeId === $contentTypeId));
    }

    private function toRevision(Entry $entry): EntryRevision
    {
        return new EntryRevision($entry->version, $entry->title, $entry->slug, $entry->values, $entry->updatedAt);
    }
}
