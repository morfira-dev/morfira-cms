<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\Entry;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntrySlug;
use Cms\Schema\Application\ValidateEntryValues;

final readonly class CreateEntry
{
    public function __construct(private EntryRepository $entries, private ValidateEntryValues $validator, private SanitizeEntryValues $sanitizer)
    {
    }

    /** @param array<string,mixed> $values */
    public function __invoke(string $contentTypeId, string $title, string $slug, array $values): EntryView
    {
        $validated = ($this->validator)($contentTypeId, ($this->sanitizer)($contentTypeId, $values));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $entry = Entry::create(EntryId::new(), $contentTypeId, $title, new EntrySlug($slug), $validated, $now);
        $this->entries->add($entry);

        return EntryView::fromEntry($entry);
    }
}
