<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\EntryDeletionBlocked;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;

final readonly class DeleteEntry
{
    public function __construct(private EntryRepository $entries, private ContentDeletionGateway $deletion)
    {
    }

    public function __invoke(string $entryId): void
    {
        $id = EntryId::fromString($entryId);
        if ($this->entries->get($id) === null) {
            throw new EntryNotFound();
        }
        if ($this->deletion->referencesToEntry($id->value) !== []) {
            throw new EntryDeletionBlocked();
        }

        $this->deletion->deleteEntry($id->value);
    }
}
