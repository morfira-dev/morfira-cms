<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Publishing\Application\ListPublishedPages\ListPublishedPages;
use Cms\Publishing\Application\PublishedPageView;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SeoController
{
    public function __construct(
        private ListPublishedPages $listPublishedPages,
        #[Autowire('%env(PUBLIC_SITE_URL)%')]
        private string $publicSiteUrl,
    ) {
    }

    #[Route('/robots.txt', name: 'publishing.seo.robots', methods: ['GET'], priority: 100)]
    public function robots(): Response
    {
        $baseUrl = rtrim($this->publicSiteUrl, '/');
        $body = "User-agent: *\nAllow: /\nSitemap: {$baseUrl}/sitemap.xml\n";

        return new Response($body, headers: [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    #[Route('/sitemap.xml', name: 'publishing.seo.sitemap', methods: ['GET'], priority: 100)]
    public function sitemap(Request $request): Response
    {
        $pages = ($this->listPublishedPages)();
        $baseUrl = rtrim($this->publicSiteUrl, '/');
        $urls = array_map(
            static fn (PublishedPageView $page): string => sprintf(
                "  <url><loc>%s</loc><lastmod>%s</lastmod></url>",
                htmlspecialchars($baseUrl . ($page->url === '/home' ? '/' : $page->url), ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($page->publishedAt, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            ),
            $pages,
        );
        $body = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . implode("\n", $urls)
            . "\n</urlset>\n";

        $etag = hash('sha256', $body);
        $response = new Response($body, headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(300);
        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }
}
