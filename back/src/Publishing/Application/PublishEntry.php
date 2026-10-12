<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Publishing\Domain\PublishedEntry;
use Cms\Publishing\Domain\PublishedEntryRepository;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Taxonomy\Application\EntryTerms;

final readonly class PublishEntry
{
    public function __construct(private EntryRepository $entries, private PublishedEntryRepository $published, private GetContentTypeDefinition $definitions, private ValidateEntryValues $validator, private ?EntryTerms $entryTerms = null)
    {
    }

    public function __invoke(string $id, int $version): PublishedEntryView
    {
        $entryId = EntryId::fromString($id);
        $entry = $this->entries->get($entryId);
        if (null === $entry) {
            throw new EntryNotFound();
        }
        if ($entry->version !== $version) {
            throw new EntryVersionConflict();
        }

        $existing = $this->published->findByVersion($entryId->value, $version);
        if (null !== $existing) {
            return PublishedEntryView::fromEntry($this->published->publish($existing));
        }

        $definition = $this->definitions->byId($entry->contentTypeId);
        $values = ($this->validator)($entry->contentTypeId, $entry->values);
        $snapshot = new PublishedEntry(EntryId::new()->value, $entry->id->value, $entry->version, ['id' => $definition->id, 'name' => $definition->name, 'apiName' => $definition->apiName, 'version' => $definition->version, 'fields' => $definition->fields], $entry->title, $entry->slug->value, $values, $this->entryTerms?->snapshot($entry->id->value) ?? [], new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        return PublishedEntryView::fromEntry($this->published->publish($snapshot));
    }
}
