<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\EntryRepository;

final readonly class ListEntries
{
    public function __construct(private EntryRepository $entries)
    {
    }

    /** @return list<EntryView> */
    public function __invoke(?string $contentTypeId = null): array
    {
        return array_map(EntryView::fromEntry(...), $this->entries->all($contentTypeId));
    }
}
