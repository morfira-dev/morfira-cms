<?php

declare(strict_types=1);

namespace Cms\Publishing\Application\GetPublishedPage;

use Cms\Publishing\Application\PublishedPageView;
use Cms\Publishing\Domain\PublishedPageRepository;

final readonly class GetPublishedPage
{
    public function __construct(private PublishedPageRepository $pages)
    {
    }

    public function __invoke(string $slug): ?PublishedPageView
    {
        $page = $this->pages->findBySlug($slug);

        if ($page === null) {
            return null;
        }
        $alternates = array_map(
            static fn ($translation): array => ['locale' => $translation->locale, 'url' => '/' . $translation->resolvedPublicPath()],
            $this->pages->activeTranslations($page->resolvedTranslationGroupId()),
        );

        return PublishedPageView::fromPublishedPage($page, $alternates);
    }
}
