<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

interface PublishedPageRepository
{
    public function publish(PublishedPage $page): PublishedPage;

    public function findBySlug(string $slug): ?PublishedPage;

    public function findRedirectTargetBySlug(string $slug): ?PublishedPage;

    /** @return list<PublishedRedirect> */
    public function redirectsForPage(string $pageId): array;

    /** @return list<PublishedPage> */
    public function allActive(): array;
}
