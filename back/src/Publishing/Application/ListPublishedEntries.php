<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\PublishedEntryRepository;

final readonly class ListPublishedEntries
{
    public function __construct(private PublishedEntryRepository $entries)
    {
    }

    /** @return array{items:list<PublishedEntryView>,total:int,limit:int,offset:int} */
    public function __invoke(string $type, int $limit, int $offset, ?string $taxonomy = null, ?string $term = null): array
    {
        return [
            'items' => array_map(PublishedEntryView::fromEntry(...), $this->entries->list($type, $limit, $offset, $taxonomy, $term)),
            'total' => $this->entries->count($type, $taxonomy, $term),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }
}
