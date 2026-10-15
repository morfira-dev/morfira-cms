<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;

final readonly class ListEntryRevisions
{
    public function __construct(private EntryRepository $entries)
    {
    }

    /** @return list<EntryRevisionView> */
    public function __invoke(string $entryId): array
    {
        $id = EntryId::fromString($entryId);
        if (null === $this->entries->get($id)) {
            throw new EntryNotFound();
        }

        return array_map(EntryRevisionView::fromRevision(...), $this->entries->revisions($id));
    }
}
