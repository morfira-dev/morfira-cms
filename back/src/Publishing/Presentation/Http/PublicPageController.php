<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Navigation\Application\GetPublishedNavigation\GetPublishedNavigation;
use Cms\Navigation\Application\PublishedNavigationView;
use Cms\Publishing\Application\GetPublishedPage\GetPublishedPage;
use Cms\Publishing\Application\GetPublishedRedirect\GetPublishedRedirect;
use Cms\Publishing\Application\PublishedPageView;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class PublicPageController
{
    public function __construct(
        private GetPublishedPage $getPublishedPage,
        private GetPublishedRedirect $getPublishedRedirect,
        private Environment $twig,
        #[Autowire('%env(PUBLIC_SITE_URL)%')]
        private string $publicSiteUrl,
        private ?GetPublishedNavigation $getPublishedNavigation = null,
    ) {
    }

    #[Route('/', name: 'publishing.pages.home', methods: ['GET'], priority: -100)]
    public function home(Request $request): Response
    {
        return $this->render('home', $request);
    }

    #[Route('/{slug}', name: 'publishing.pages.web', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'], priority: -100)]
    public function page(string $slug, Request $request): Response
    {
        return $this->render($slug, $request);
    }

    private function render(string $slug, Request $request): Response
    {
        $published = ($this->getPublishedPage)($slug);

        if ($published === null) {
            $redirectTarget = ($this->getPublishedRedirect)($slug);
            if ($redirectTarget !== null) {
                $targetUrl = $this->publicUrl($redirectTarget->slug);
                $response = new RedirectResponse($targetUrl, 301);
                $response->setPublic();
                $response->setMaxAge(300);

                return $response;
            }

            return $this->renderNotFound($request);
        }

        return $published->slug === '404'
            ? $this->renderPublished($published, $request, 404, true)
            : $this->renderPublished($published, $request);
    }

    private function renderNotFound(Request $request): Response
    {
        $notFoundPage = ($this->getPublishedPage)('404');
        if ($notFoundPage !== null) {
            return $this->renderPublished($notFoundPage, $request, 404, true);
        }

        $response = new Response(
            $this->twig->render('delivery/not_found.html.twig'),
            404,
            ['X-Robots-Tag' => 'noindex, nofollow'],
        );
        $response->setPublic();
        $response->setMaxAge(60);

        return $response;
    }

    private function renderPublished(PublishedPageView $published, Request $request, int $status = 200, bool $notFound = false): Response
    {
        $baseUrl = rtrim($this->publicSiteUrl, '/');
        $headerNavigation = $this->navigation($published->layout['headerNavigationCode']);
        $footerNavigation = $this->navigation($published->layout['footerNavigationCode']);
        $response = new Response($this->twig->render('delivery/page.html.twig', [
            'page' => $published,
            'publicSiteUrl' => $baseUrl,
            'canonicalUrl' => $notFound ? null : $this->publicUrl($published->slug),
            'notFound' => $notFound,
            'headerNavigation' => $headerNavigation,
            'footerNavigation' => $footerNavigation,
        ]), $status);
        $response->setPublic();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(300);
        $response->setEtag($published->snapshotId);
        if ($notFound || $published->seo['noIndex']) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        $response->isNotModified($request);

        return $response;
    }

    private function navigation(?string $code): ?PublishedNavigationView
    {
        return $code === null || $this->getPublishedNavigation === null ? null : ($this->getPublishedNavigation)($code);
    }

    private function publicUrl(string $slug): string
    {
        $baseUrl = rtrim($this->publicSiteUrl, '/');

        return $baseUrl . ($slug === 'home' ? '/' : '/' . $slug);
    }
}
