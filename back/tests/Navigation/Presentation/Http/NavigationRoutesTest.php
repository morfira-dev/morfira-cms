<?php

declare(strict_types=1);

namespace Cms\Tests\Navigation\Presentation\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class NavigationRoutesTest extends KernelTestCase
{
    #[Test]
    public function navigation_http_adapters_are_registered(): void
    {
        self::bootKernel();

        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $routes = $router->getRouteCollection();
        self::assertNotNull($routes->get('navigation.menus.list'));
        self::assertNotNull($routes->get('navigation.menus.create'));
        self::assertNotNull($routes->get('navigation.menus.get'));
        self::assertNotNull($routes->get('navigation.menus.update'));
        self::assertNotNull($routes->get('navigation.menus.publish'));
        self::assertNotNull($routes->get('navigation.public.get'));
    }
}
