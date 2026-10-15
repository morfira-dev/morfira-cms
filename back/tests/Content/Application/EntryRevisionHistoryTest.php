<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\ListEntryRevisions;
use Cms\Content\Application\RestoreEntryRevision;
use Cms\Content\Application\SanitizeEntryValues;
use Cms\Content\Application\UpdateEntry;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EntryRevisionHistoryTest extends TestCase
{
    #[Test]
    public function restore_appends_a_new_validated_revision_without_mutating_history(): void
    {
        [$entries, $entryId, $validator, $sanitizer] = $this->fixture();
        (new UpdateEntry($entries, $validator, $sanitizer))($entryId, 'Second', 'second', ['body' => 'changed'], 1);

        $restored = (new RestoreEntryRevision($entries, $sanitizer, $validator))($entryId, 1, 2);
        $history = (new ListEntryRevisions($entries))($entryId);

        self::assertSame(3, $restored->version);
        self::assertSame('First', $restored->title);
        self::assertSame([3, 2, 1], array_map(static fn ($revision): int => $revision->revision, $history));
        self::assertSame('First', $history[2]->title);
    }

    #[Test]
    public function stale_restore_is_rejected_without_appending_a_revision(): void
    {
        [$entries, $entryId, $validator, $sanitizer] = $this->fixture();
        (new UpdateEntry($entries, $validator, $sanitizer))($entryId, 'Second', 'second', ['body' => 'changed'], 1);
        try {
            (new RestoreEntryRevision($entries, $sanitizer, $validator))($entryId, 1, 1);
            self::fail('A stale restore must fail.');
        } catch (EntryVersionConflict) {
            self::assertCount(2, (new ListEntryRevisions($entries))($entryId));
        }
    }

    /** @return array{InMemoryEntryRepository,string,ValidateEntryValues,SanitizeEntryValues} */
    private function fixture(): array
    {
        $types = new InMemoryContentTypeRepository();
        $type = (new CreateContentType($types))('Article', 'articles', [['id' => '11111111-1111-4111-8111-111111111111', 'key' => 'body', 'label' => 'Body', 'type' => 'text', 'required' => true]]);
        $definitions = new GetContentTypeDefinition($types);
        $validator = new ValidateEntryValues($definitions);
        $sanitizer = new SanitizeEntryValues($definitions, new PassThroughRichTextSanitizer());
        $entries = new InMemoryEntryRepository();
        $entry = (new CreateEntry($entries, $validator, $sanitizer))($type->id, 'First', 'first', ['body' => 'original']);

        return [$entries, $entry->id, $validator, $sanitizer];
    }
}
