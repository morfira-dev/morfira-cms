<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Presentation\Http;

use Cms\Publishing\Application\GetPublishedPage\GetPublishedPage;
use Cms\Publishing\Application\GetPublishedRedirect\GetPublishedRedirect;
use Cms\Publishing\Domain\PublishedPage;
use Cms\Publishing\Presentation\Http\PublicPageController;
use Cms\Tests\Publishing\InMemoryPublishedPageRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class PublicPageControllerTest extends TestCase
{
    #[Test]
    public function it_sends_header_level_noindex_for_hidden_snapshots(): void
    {
        $repository = new InMemoryPublishedPageRepository();
        $repository->publish(new PublishedPage(
            '11111111-1111-4111-8111-111111111111',
            '22222222-2222-4222-8222-222222222222',
            1,
            'Hidden page',
            'hidden-page',
            [],
            ['title' => '', 'description' => '', 'noIndex' => true, 'openGraphImageAssetId' => null],
            new \DateTimeImmutable('2026-10-10T12:00:00+00:00'),
        ));
        $twig = new Environment(new ArrayLoader(['delivery/page.html.twig' => 'rendered']));
        $controller = new PublicPageController(
            new GetPublishedPage($repository),
            new GetPublishedRedirect($repository),
            $twig,
            'https://example.com',
        );

        $response = $controller->page('hidden-page', Request::create('/hidden-page'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertSame('"11111111-1111-4111-8111-111111111111"', $response->getEtag());
    }

    #[Test]
    public function it_redirects_a_historical_slug_to_the_current_public_url(): void
    {
        $repository = new InMemoryPublishedPageRepository();
        $repository->publish($this->page('11111111-1111-4111-8111-111111111111', 1, 'old-slug'));
        $repository->publish($this->page('22222222-2222-4222-8222-222222222222', 2, 'current-slug'));
        $controller = $this->controller($repository);

        $response = $controller->page('old-slug', Request::create('/old-slug'));

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('https://example.com/current-slug', $response->headers->get('Location'));
        self::assertSame('300', $response->headers->getCacheControlDirective('max-age'));
    }

    #[Test]
    public function it_uses_the_safe_builtin_not_found_page_when_no_cms_page_exists(): void
    {
        $controller = $this->controller(new InMemoryPublishedPageRepository());

        $response = $controller->page('missing', Request::create('/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertSame('fallback', $response->getContent());
    }

    #[Test]
    public function it_renders_the_managed_404_snapshot_with_404_semantics(): void
    {
        $repository = new InMemoryPublishedPageRepository();
        $repository->publish($this->page('44444444-4444-4444-8444-444444444444', 1, '404'));
        $controller = $this->controller($repository);

        $response = $controller->page('missing', Request::create('/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('rendered', $response->getContent());
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertSame('"44444444-4444-4444-8444-444444444444"', $response->getEtag());
    }

    private function controller(InMemoryPublishedPageRepository $repository): PublicPageController
    {
        $twig = new Environment(new ArrayLoader([
            'delivery/page.html.twig' => 'rendered',
            'delivery/not_found.html.twig' => 'fallback',
        ]));

        return new PublicPageController(
            new GetPublishedPage($repository),
            new GetPublishedRedirect($repository),
            $twig,
            'https://example.com',
        );
    }

    private function page(string $snapshotId, int $revision, string $slug): PublishedPage
    {
        return new PublishedPage(
            $snapshotId,
            '33333333-3333-4333-8333-333333333333',
            $revision,
            'Page',
            $slug,
            [],
            ['title' => '', 'description' => '', 'noIndex' => false, 'openGraphImageAssetId' => null],
            new \DateTimeImmutable(sprintf('2026-10-11T12:00:0%d+00:00', $revision)),
        );
    }
}
