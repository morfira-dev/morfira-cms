<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;

final readonly class GetEntry
{
    public function __construct(private EntryRepository $entries)
    {
    }

    public function __invoke(string $id): EntryView
    {
        $entry = $this->entries->get(EntryId::fromString($id));
        if (null === $entry) {
            throw new EntryNotFound();
        }

        return EntryView::fromEntry($entry);
    }
}
