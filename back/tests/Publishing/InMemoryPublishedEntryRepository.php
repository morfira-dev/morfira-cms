<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing;

use Cms\Publishing\Domain\PublishedEntry;
use Cms\Publishing\Domain\PublishedEntryRepository;

final class InMemoryPublishedEntryRepository implements PublishedEntryRepository
{
    /** @var array<string, PublishedEntry> */ private array $snapshots = [];
    /** @var array<string, string> */ private array $routes = [];

    public function publish(PublishedEntry $entry): PublishedEntry
    {
        $key = $entry->entryId.':'.$entry->sourceVersion;
        $snapshot = $this->snapshots[$key] ?? $entry;
        $this->snapshots[$key] = $snapshot;
        foreach ($this->routes as $route => $active) {
            if (str_starts_with($active, $entry->entryId.':')) {
                unset($this->routes[$route]);
            }
        }
        $this->routes[$snapshot->contentType['apiName'].'/'.$snapshot->slug] = $key;

        return $snapshot;
    }

    public function unpublish(string $entryId): void
    {
        foreach ($this->routes as $route => $key) {
            if (str_starts_with($key, $entryId.':')) {
                unset($this->routes[$route]);
            }
        }
    }

    public function find(string $apiName, string $slug): ?PublishedEntry
    {
        $key = $this->routes[$apiName.'/'.$slug] ?? null;

        return null === $key ? null : ($this->snapshots[$key] ?? null);
    }

    public function findByVersion(string $entryId, int $sourceVersion): ?PublishedEntry
    {
        return $this->snapshots[$entryId.':'.$sourceVersion] ?? null;
    }

    public function list(string $apiName, int $limit, int $offset, ?string $taxonomy = null, ?string $term = null): array
    {
        $items = [];
        foreach ($this->routes as $route => $key) {
            if (str_starts_with($route, $apiName.'/') && isset($this->snapshots[$key])) {
                $snapshot=$this->snapshots[$key];
                if($taxonomy!==null&&!array_any($snapshot->taxonomies,static fn(array $item):bool=>$item['taxonomySlug']===$taxonomy&&$item['termSlug']===$term))continue;
                $items[] = $snapshot;
            }
        }

        return array_slice($items, $offset, $limit);
    }

    public function count(string $apiName, ?string $taxonomy = null, ?string $term = null): int
    {
        return count($this->list($apiName, PHP_INT_MAX, 0, $taxonomy, $term));
    }
}
