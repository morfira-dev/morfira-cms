<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\GetPage\GetPage;
use Cms\Content\Application\ManagePageReviews;
use Cms\Content\Domain\PageNotFound;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Twig\Environment;

final readonly class PagePreviewController
{
    private const array PRIVATE_HEADERS = [
        'Cache-Control' => 'private, no-store',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];

    public function __construct(
        private GetPage $getPage,
        private ManagePageReviews $reviews,
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
            headers: self::PRIVATE_HEADERS,
        );
    }

    #[Route('/_preview/page-reviews/{id}', name: 'content.page_reviews.preview', methods: ['GET'])]
    public function review(string $id): Response
    {
        $revision = preg_match('~\A' . Requirement::UUID_V4 . '\z~', $id) === 1
            ? $this->reviews->preview($id)
            : null;
        if ($revision === null) {
            return new Response('Page review preview not found.', 404, self::PRIVATE_HEADERS);
        }

        return new Response(
            $this->twig->render('content/page.html.twig', ['page' => $revision, 'review_preview' => true]),
            headers: self::PRIVATE_HEADERS,
        );
    }
}
