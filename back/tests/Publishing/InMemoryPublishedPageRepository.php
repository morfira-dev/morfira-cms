<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing;

use Cms\Publishing\Domain\PublishedPage;
use Cms\Publishing\Domain\PublishedPageRepository;
use Cms\Publishing\Domain\PublishedRedirect;
use Cms\Publishing\Domain\PublishedSlugAlreadyExists;

final class InMemoryPublishedPageRepository implements PublishedPageRepository
{
    /** @var array<string, PublishedPage> */
    private array $snapshots = [];

    /** @var array<string, string> */
    private array $routes = [];

    /** @var array<string, string> source slug to page id */
    private array $redirects = [];

    public function publish(PublishedPage $page): PublishedPage
    {
        $revisionKey = $page->pageId . ':' . $page->sourceRevision;
        $snapshot = $this->snapshots[$revisionKey] ?? $page;

        $redirectOwner = $this->redirects[$snapshot->slug] ?? null;
        if ($redirectOwner !== null && $redirectOwner !== $snapshot->pageId) {
            throw new PublishedSlugAlreadyExists($snapshot->slug);
        }
        if (isset($this->routes[$snapshot->slug]) && !str_starts_with($this->routes[$snapshot->slug], $snapshot->pageId . ':')) {
            throw new PublishedSlugAlreadyExists($snapshot->slug);
        }

        $this->snapshots[$revisionKey] = $snapshot;

        foreach ($this->routes as $slug => $activeRevisionKey) {
            if (str_starts_with($activeRevisionKey, $page->pageId . ':')) {
                if ($slug !== $snapshot->slug) {
                    $this->redirects[$slug] = $snapshot->pageId;
                }
                unset($this->routes[$slug]);
            }
        }
        if (($this->redirects[$snapshot->slug] ?? null) === $snapshot->pageId) {
            unset($this->redirects[$snapshot->slug]);
        }
        $this->routes[$snapshot->slug] = $revisionKey;

        return $snapshot;
    }

    public function unpublish(string $pageId): void
    {
        foreach ($this->routes as $slug => $revisionKey) {
            if (str_starts_with($revisionKey, $pageId . ':')) {
                unset($this->routes[$slug]);
            }
        }
        foreach ($this->redirects as $slug => $redirectPageId) {
            if ($redirectPageId === $pageId) {
                unset($this->redirects[$slug]);
            }
        }
    }

    public function findBySlug(string $slug): ?PublishedPage
    {
        $revisionKey = $this->routes[$slug] ?? null;
        if ($revisionKey === null) {
            return null;
        }

        return $this->snapshots[$revisionKey] ?? null;
    }

    public function findRedirectTargetBySlug(string $slug): ?PublishedPage
    {
        $pageId = $this->redirects[$slug] ?? null;
        if ($pageId === null) {
            return null;
        }

        foreach ($this->routes as $revisionKey) {
            if (str_starts_with($revisionKey, $pageId . ':')) {
                return $this->snapshots[$revisionKey] ?? null;
            }
        }

        return null;
    }

    public function redirectsForPage(string $pageId): array
    {
        $target = null;
        foreach ($this->routes as $slug => $revisionKey) {
            if (str_starts_with($revisionKey, $pageId . ':')) {
                $target = $slug;
                break;
            }
        }
        if ($target === null) {
            return [];
        }

        $redirects = [];
        foreach ($this->redirects as $source => $redirectPageId) {
            if ($redirectPageId === $pageId) {
                $redirects[] = new PublishedRedirect($source, $target);
            }
        }

        return $redirects;
    }

    public function allActive(): array
    {
        $pages = [];
        foreach ($this->routes as $revisionKey) {
            if (isset($this->snapshots[$revisionKey])) {
                $pages[] = $this->snapshots[$revisionKey];
            }
        }

        return $pages;
    }
}
