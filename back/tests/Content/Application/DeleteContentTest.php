<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\DeleteEntry;
use Cms\Content\Application\DeletePage;
use Cms\Content\Application\SanitizeEntryValues;
use Cms\Content\Domain\EntryDeletionBlocked;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryContentDeletionGateway;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Content\InMemoryPageRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeleteContentTest extends TestCase
{
    #[Test]
    public function page_deletion_is_delegated_for_an_existing_draft(): void
    {
        $pages = new InMemoryPageRepository();
        $page = (new CreatePage($pages))('Landing', 'landing');
        $deletion = new InMemoryContentDeletionGateway();

        (new DeletePage($pages, $deletion))($page->id);

        self::assertSame([$page->id], $deletion->deletedPages);
    }

    #[Test]
    public function entry_deletion_is_blocked_while_another_draft_references_it(): void
    {
        [$entries, $entryId] = $this->entryFixture();
        $deletion = new InMemoryContentDeletionGateway();
        $deletion->references[$entryId] = [[
            'id' => '22222222-2222-4222-8222-222222222222',
            'contentTypeId' => '33333333-3333-4333-8333-333333333333',
            'title' => 'Related entry',
            'fieldKey' => 'featuredArticle',
        ]];

        $this->expectException(EntryDeletionBlocked::class);

        (new DeleteEntry($entries, $deletion))($entryId);
    }

    #[Test]
    public function unreferenced_entry_deletion_is_delegated(): void
    {
        [$entries, $entryId] = $this->entryFixture();
        $deletion = new InMemoryContentDeletionGateway();

        (new DeleteEntry($entries, $deletion))($entryId);

        self::assertSame([$entryId], $deletion->deletedEntries);
    }

    /** @return array{InMemoryEntryRepository,string} */
    private function entryFixture(): array
    {
        $types = new InMemoryContentTypeRepository();
        $type = (new CreateContentType($types))('Article', 'articles', []);
        $entries = new InMemoryEntryRepository();
        $definitions = new GetContentTypeDefinition($types);
        $entry = (new CreateEntry($entries, new ValidateEntryValues($definitions), new SanitizeEntryValues($definitions, new PassThroughRichTextSanitizer())))($type->id, 'First', 'first', []);

        return [$entries, $entry->id];
    }
}
