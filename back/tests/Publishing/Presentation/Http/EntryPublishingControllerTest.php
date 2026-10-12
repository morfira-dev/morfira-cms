<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Presentation\Http;

use Cms\Publishing\Application\GetPublishedEntry;
use Cms\Publishing\Application\ListPublishedEntries;
use Cms\Publishing\Application\PublishEntry;
use Cms\Publishing\Domain\PublishedEntry;
use Cms\Publishing\Presentation\Http\EntryPublishingController;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Publishing\InMemoryPublishedEntryRepository;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class EntryPublishingControllerTest extends TestCase
{
    #[Test]
    public function public_collection_is_paginated_and_single_reads_are_conditional(): void
    {
        $types = new InMemoryContentTypeRepository();
        $type = (new CreateContentType($types))('Article', 'articles', []);
        $definitions = new GetContentTypeDefinition($types);
        $published = new InMemoryPublishedEntryRepository();
        $snapshot = new PublishedEntry(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            1,
            ['id' => $type->id, 'name' => 'Article', 'apiName' => 'articles', 'version' => 1, 'fields' => []],
            'First',
            'first',
            [],
            new \DateTimeImmutable('2026-10-11T12:00:00+00:00'),
        );
        $published->publish($snapshot);
        $entries = new InMemoryEntryRepository();
        $validator = new ValidateEntryValues($definitions);
        $controller = new EntryPublishingController(
            new PublishEntry($entries, $published, $definitions, $validator),
            new GetPublishedEntry($published),
            new ListPublishedEntries($published),
        );

        $collection = $controller->list('articles', Request::create('/api/v1/content/entries/articles?limit=1&offset=0'));
        $payload = json_decode((string) $collection->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $items = $payload['items'] ?? null;
        self::assertIsArray($items);
        $first = $items[0] ?? null;
        self::assertIsArray($first);
        $conditional = Request::create('/api/v1/content/entries/articles/first', server: ['HTTP_IF_NONE_MATCH' => '"11111111-1111-4111-8111-111111111111"']);
        $single = $controller->get('articles', 'first', $conditional);

        self::assertSame(1, $payload['total']);
        self::assertSame(1, $payload['limit']);
        self::assertSame('First', $first['title']);
        self::assertSame(304, $single->getStatusCode());
    }

    #[Test]
    public function invalid_pagination_is_rejected(): void
    {
        $types = new InMemoryContentTypeRepository();
        (new CreateContentType($types))('Article', 'articles', []);
        $definitions = new GetContentTypeDefinition($types);
        $published = new InMemoryPublishedEntryRepository();
        $entries = new InMemoryEntryRepository();
        $validator = new ValidateEntryValues($definitions);
        $controller = new EntryPublishingController(new PublishEntry($entries, $published, $definitions, $validator), new GetPublishedEntry($published), new ListPublishedEntries($published));

        self::assertSame(422, $controller->list('articles', Request::create('/api/v1/content/entries/articles?limit=101'))->getStatusCode());
    }

    #[Test]
    public function unpublished_types_return_an_empty_collection(): void
    {
        $types = new InMemoryContentTypeRepository();
        $definitions = new GetContentTypeDefinition($types);
        $published = new InMemoryPublishedEntryRepository();
        $entries = new InMemoryEntryRepository();
        $validator = new ValidateEntryValues($definitions);
        $controller = new EntryPublishingController(new PublishEntry($entries, $published, $definitions, $validator), new GetPublishedEntry($published), new ListPublishedEntries($published));

        $response = $controller->list('unknown-type', Request::create('/api/v1/content/entries/unknown-type'));
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $payload['items']);
        self::assertSame(0, $payload['total']);
    }
}
