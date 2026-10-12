<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Presentation\Http;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\ListPageRevisions;
use Cms\Content\Application\RestorePageRevision;
use Cms\Content\Presentation\Http\PageRevisionController;
use Cms\Tests\Content\InMemoryPageRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PageRevisionControllerTest extends TestCase
{
    #[Test]
    public function it_exposes_history_and_restores_through_the_http_contract(): void
    {
        $pages = new InMemoryPageRepository();
        $page = (new CreatePage($pages))('Landing', 'landing');
        $controller = new PageRevisionController(new ListPageRevisions($pages), new RestorePageRevision($pages));

        self::assertSame(200, $controller->list($page->id)->getStatusCode());
        $request = Request::create('/restore', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"version":1}');
        $response = $controller->restore($page->id, 1, $request);
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame(2, $payload['version'] ?? null);
    }
}
