<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;

final readonly class ListEntryReferences
{
    public function __construct(private EntryRepository $entries, private ContentDeletionGateway $references)
    {
    }

    /** @return list<array{id:string,contentTypeId:string,title:string,fieldKey:string}> */
    public function __invoke(string $entryId): array
    {
        $id = EntryId::fromString($entryId);
        if (null === $this->entries->get($id)) {
            throw new EntryNotFound();
        }

        return $this->references->referencesToEntry($id->value);
    }
}
