<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Application;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\UpdateEntry;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Publishing\Application\GetPublishedEntry;
use Cms\Publishing\Application\ListPublishedEntries;
use Cms\Publishing\Application\PublishEntry;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\UpdateContentType;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Publishing\InMemoryPublishedEntryRepository;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PublishEntryTest extends TestCase
{
    #[Test]
    public function publication_is_idempotent_and_immutable(): void
    {
        [$publish, $get, $entries, $created] = $this->fixture();
        $first = $publish($created->id, 1);
        $second = $publish($created->id, 1);
        (new UpdateEntry($entries, $this->validator))($created->id, 'Changed', 'first', ['body' => 'changed'], 1);
        $public = $get('articles', 'first');
        self::assertSame($first->snapshotId, $second->snapshotId);
        self::assertNotNull($public);
        self::assertSame('First', $public->title);
        self::assertSame('body', $public->contentType['fields'][0]['key']);
    }

    #[Test]
    public function stale_version_is_rejected(): void
    {
        [$publish, , $entries, $created] = $this->fixture();
        (new UpdateEntry($entries, $this->validator))($created->id, 'Changed', 'first', ['body' => 'changed'], 1);
        $this->expectException(EntryVersionConflict::class);
        $publish($created->id, 1);
    }

    #[Test]
    public function public_reads_do_not_depend_on_the_mutable_schema_api_name(): void
    {
        [$publish, $get, , $created, $types, $type, $published] = $this->fixture();
        $first = $publish($created->id, 1);
        (new UpdateContentType($types))($type->id, 'Article', 'renamed-articles', $type->fields, 1);
        $retried = $publish($created->id, 1);

        $public = $get('articles', 'first');
        $collection = (new ListPublishedEntries($published))('articles', 20, 0);

        self::assertNotNull($public);
        self::assertSame($first->snapshotId, $retried->snapshotId);
        self::assertSame('articles', $public->contentType['apiName']);
        self::assertSame(1, $collection['total']);
    }

    private ValidateEntryValues $validator;

    /** @return array{PublishEntry, GetPublishedEntry, InMemoryEntryRepository, \Cms\Content\Application\EntryView, InMemoryContentTypeRepository, \Cms\Schema\Application\ContentTypeView, InMemoryPublishedEntryRepository} */
    private function fixture(): array
    {
        $types = new InMemoryContentTypeRepository();
        $definition = new GetContentTypeDefinition($types);
        $this->validator = new ValidateEntryValues($definition);
        $type = (new CreateContentType($types))('Article', 'articles', [['id' => '11111111-1111-4111-8111-111111111111', 'key' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => true]]);
        $entries = new InMemoryEntryRepository();
        $created = (new CreateEntry($entries, $this->validator))($type->id, 'First', 'first', ['body' => 'original']);
        $published = new InMemoryPublishedEntryRepository();

        return [new PublishEntry($entries, $published, $definition, $this->validator), new GetPublishedEntry($published), $entries, $created, $types, $type, $published];
    }
}
