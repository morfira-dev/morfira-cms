<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\ListEntryReferences;
use Cms\Content\Application\SanitizeEntryValues;
use Cms\Content\Domain\EntryNotFound;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryContentDeletionGateway;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListEntryReferencesTest extends TestCase
{
    #[Test]
    public function it_returns_mutable_incoming_references_for_an_existing_entry(): void
    {
        $types = new InMemoryContentTypeRepository();
        $type = (new CreateContentType($types))('Article', 'articles', []);
        $definitions = new GetContentTypeDefinition($types);
        $entries = new InMemoryEntryRepository();
        $entry = (new CreateEntry($entries, new ValidateEntryValues($definitions), new SanitizeEntryValues($definitions, new PassThroughRichTextSanitizer())))($type->id, 'Target', 'target', []);
        $gateway = new InMemoryContentDeletionGateway();
        $gateway->references[$entry->id] = [[
            'id' => '22222222-2222-4222-8222-222222222222',
            'contentTypeId' => $type->id,
            'title' => 'Source',
            'fieldKey' => 'related',
        ]];

        self::assertSame($gateway->references[$entry->id], (new ListEntryReferences($entries, $gateway))($entry->id));
    }

    #[Test]
    public function it_rejects_a_missing_entry(): void
    {
        $this->expectException(EntryNotFound::class);
        (new ListEntryReferences(new InMemoryEntryRepository(), new InMemoryContentDeletionGateway()))('11111111-1111-4111-8111-111111111111');
    }
}
