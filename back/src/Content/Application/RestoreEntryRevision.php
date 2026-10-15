<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntryRevisionNotFound;
use Cms\Schema\Application\ValidateEntryValues;

final readonly class RestoreEntryRevision
{
    public function __construct(private EntryRepository $entries, private SanitizeEntryValues $sanitizer, private ValidateEntryValues $validator)
    {
    }

    public function __invoke(string $entryId, int $revisionNumber, int $expectedVersion): EntryView
    {
        $id = EntryId::fromString($entryId);
        $entry = $this->entries->get($id);
        if (null === $entry) {
            throw new EntryNotFound();
        }
        $revision = $this->entries->revision($id, $revisionNumber);
        if (null === $revision) {
            throw new EntryRevisionNotFound();
        }
        $values = ($this->validator)($entry->contentTypeId, ($this->sanitizer)($entry->contentTypeId, $revision->values));
        $restored = $entry->revise($revision->title, $revision->slug, $values, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->entries->save($restored, $expectedVersion);

        return EntryView::fromEntry($restored);
    }
}
