<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

interface PublishedEntryRepository
{
    public function publish(PublishedEntry $entry): PublishedEntry;

    public function findByVersion(string $entryId, int $sourceVersion): ?PublishedEntry;

    public function find(string $apiName, string $slug): ?PublishedEntry;

    /** @return list<PublishedEntry> */
    public function list(string $apiName, int $limit, int $offset, ?string $taxonomy = null, ?string $term = null): array;

    public function count(string $apiName, ?string $taxonomy = null, ?string $term = null): int;
}
