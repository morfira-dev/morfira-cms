<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntrySlug;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Schema\Application\ValidateEntryValues;

final readonly class UpdateEntry
{
    public function __construct(private EntryRepository $entries, private ValidateEntryValues $validator, private SanitizeEntryValues $sanitizer)
    {
    }

    /** @param array<string,mixed> $values */
    public function __invoke(string $id, string $title, string $slug, array $values, int $version): EntryView
    {
        $entry = $this->entries->get(EntryId::fromString($id));
        if (null === $entry) {
            throw new EntryNotFound();
        }
        if ($entry->version !== $version) {
            throw new EntryVersionConflict();
        }

        $updated = $entry->revise(
            $title,
            new EntrySlug($slug),
            ($this->validator)($entry->contentTypeId, ($this->sanitizer)($entry->contentTypeId, $values)),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        $this->entries->save($updated, $version);

        return EntryView::fromEntry($updated);
    }
}
