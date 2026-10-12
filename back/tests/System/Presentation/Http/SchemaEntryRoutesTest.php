<?php

declare(strict_types=1);

namespace Cms\Tests\System\Presentation\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class SchemaEntryRoutesTest extends KernelTestCase
{
    #[Test]
    public function schema_and_entry_routes_are_registered(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = $router->getRouteCollection();

        self::assertSame('/api/v1/admin/content-types', $routes->get('schema.content_types.list')?->getPath());
        self::assertSame('/api/v1/admin/entries', $routes->get('content.entries.list')?->getPath());
        self::assertSame('/api/v1/admin/entries/{id}/publish', $routes->get('publishing.entries.publish')?->getPath());
        self::assertSame('/api/v1/admin/entries/{id}/schedule', $routes->get('publishing.entries.schedule.put')?->getPath());
        self::assertSame('/api/v1/admin/pages/{id}/schedule', $routes->get('publishing.pages.schedule.put')?->getPath());
        self::assertSame('/api/v1/admin/taxonomies', $routes->get('taxonomy.list')?->getPath());
        self::assertSame('/api/v1/admin/entries/{id}/terms', $routes->get('taxonomy.entries.put')?->getPath());
        self::assertSame('/api/v1/content/entries/{type}', $routes->get('publishing.entries.list')?->getPath());
        self::assertSame('/api/v1/content/entries/{type}/{slug}', $routes->get('publishing.entries.get')?->getPath());
        self::assertSame('/content/{type}/{slug}', $routes->get('publishing.entries.web')?->getPath());
    }
}
