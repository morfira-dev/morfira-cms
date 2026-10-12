<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Presentation\Http;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\GetEntry;
use Cms\Content\Application\ListEntries;
use Cms\Content\Application\UpdateEntry;
use Cms\Content\Presentation\Http\EntryController;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class EntryControllerTest extends TestCase
{
    #[Test]
    public function empty_values_are_accepted_and_serialized_as_an_object(): void
    {
        [$controller, $contentTypeId] = $this->controller();
        $request = Request::create('/api/v1/admin/entries', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contentTypeId' => $contentTypeId,
            'title' => 'Empty entry',
            'slug' => 'empty-entry',
            'values' => new \stdClass(),
        ], JSON_THROW_ON_ERROR));

        $response = $controller->create($request);

        self::assertSame(201, $response->getStatusCode());
        self::assertStringContainsString('"values":{}', (string) $response->getContent());
    }

    #[Test]
    public function unknown_top_level_properties_are_rejected(): void
    {
        [$controller, $contentTypeId] = $this->controller();
        $request = Request::create('/api/v1/admin/entries', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'contentTypeId' => $contentTypeId,
            'title' => 'Entry',
            'slug' => 'entry',
            'values' => new \stdClass(),
            'unexpected' => true,
        ], JSON_THROW_ON_ERROR));

        self::assertSame(422, $controller->create($request)->getStatusCode());
    }

    #[Test]
    public function listing_a_missing_content_type_returns_not_found(): void
    {
        [$controller] = $this->controller();
        $request = Request::create('/api/v1/admin/entries?contentTypeId=99999999-9999-4999-8999-999999999999');

        self::assertSame(404, $controller->list($request)->getStatusCode());
    }

    /** @return array{EntryController, string} */
    private function controller(): array
    {
        $types = new InMemoryContentTypeRepository();
        $type = (new CreateContentType($types))('Empty', 'empty', []);
        $definitions = new GetContentTypeDefinition($types);
        $validator = new ValidateEntryValues($definitions);
        $entries = new InMemoryEntryRepository();

        return [new EntryController(
            new CreateEntry($entries, $validator),
            new GetEntry($entries),
            new ListEntries($entries),
            new UpdateEntry($entries, $validator),
            $definitions,
        ), $type->id];
    }
}
