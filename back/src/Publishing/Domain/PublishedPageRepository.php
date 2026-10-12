<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

interface PublishedPageRepository
{
    public function publish(PublishedPage $page): PublishedPage;

    public function unpublish(string $pageId): void;

    public function findBySlug(string $slug): ?PublishedPage;

    public function findRedirectTargetBySlug(string $slug): ?PublishedPage;

    /** @return list<PublishedRedirect> */
    public function redirectsForPage(string $pageId): array;

    public function addRedirect(string $pageId, string $sourceSlug): PublishedRedirect;

    public function removeRedirect(string $pageId, string $sourceSlug): void;

    /** @return list<PublishedPage> */
    public function allActive(): array;

    /** @return list<PublishedPage> */
    public function activeTranslations(string $translationGroupId): array;
}
