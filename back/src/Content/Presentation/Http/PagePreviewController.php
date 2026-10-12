<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\GetPage\GetPage;
use Cms\Content\Domain\PageNotFound;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class PagePreviewController
{
    public function __construct(
        private GetPage $getPage,
        private Environment $twig,
    ) {
    }

    #[Route('/_preview/pages/{id}', name: 'content.pages.preview', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        try {
            $page = ($this->getPage)($id);
        } catch (PageNotFound|\InvalidArgumentException) {
            return new Response('Page not found.', 404);
        }

        return new Response(
            $this->twig->render('content/page.html.twig', ['page' => $page]),
            headers: [
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
        );
    }
}
