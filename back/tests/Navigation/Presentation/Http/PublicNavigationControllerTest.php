<?php

declare(strict_types=1);

namespace Cms\Tests\Navigation\Presentation\Http;

use Cms\Navigation\Application\GetPublishedNavigation\GetPublishedNavigation;
use Cms\Navigation\Domain\PublishedNavigationMenu;
use Cms\Navigation\Presentation\Http\PublicNavigationController;
use Cms\Tests\Navigation\InMemoryPublishedNavigationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PublicNavigationControllerTest extends TestCase
{
    #[Test]
    public function it_returns_the_active_snapshot_with_etag_and_honours_if_none_match(): void
    {
        $repository = new InMemoryPublishedNavigationRepository();
        $repository->publish(new PublishedNavigationMenu(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            1,
            'Main',
            'main',
            [],
            new \DateTimeImmutable('2026-10-11T12:00:00+00:00'),
        ));
        $controller = new PublicNavigationController(new GetPublishedNavigation($repository));
        $request = Request::create('/api/v1/content/navigation/main');
        $first = $controller->get('main', $request);
        $conditional = Request::create('/api/v1/content/navigation/main', server: ['HTTP_IF_NONE_MATCH' => $first->getEtag()]);
        $second = $controller->get('main', $conditional);

        self::assertSame(200, $first->getStatusCode());
        self::assertSame('"11111111-1111-4111-8111-111111111111"', $first->getEtag());
        self::assertSame(304, $second->getStatusCode());
        self::assertSame('', $second->getContent());
    }

    #[Test]
    public function draft_only_navigation_is_not_public(): void
    {
        $controller = new PublicNavigationController(new GetPublishedNavigation(new InMemoryPublishedNavigationRepository()));
        self::assertSame(404, $controller->get('main', Request::create('/api/v1/content/navigation/main'))->getStatusCode());
    }
}
