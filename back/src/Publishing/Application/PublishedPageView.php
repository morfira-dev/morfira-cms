<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\PublishedPage;

final readonly class PublishedPageView
{
    /** @param list<array{id: string, type: string, data: array<string, string>}> $blocks */
    public function __construct(
        public string $snapshotId,
        public string $pageId,
        public int $sourceRevision,
        public string $title,
        public string $slug,
        public array $blocks,
        /** @var array{title: string, description: string, noIndex: bool, openGraphImageAssetId: ?string} */
        public array $seo,
        /** @var array{theme: string, template: string, headerNavigationCode: ?string, footerNavigationCode: ?string} */
        public array $layout,
        public string $publishedAt,
        public string $locale,
        public string $translationGroupId,
        public string $url,
        /** @var list<array{locale: string, url: string}> */
        public array $alternates = [],
    ) {
    }

    /** @param list<array{locale: string, url: string}> $alternates */
    public static function fromPublishedPage(PublishedPage $page, array $alternates = []): self
    {
        return new self(
            $page->snapshotId,
            $page->pageId,
            $page->sourceRevision,
            $page->title,
            $page->slug,
            $page->blocks,
            $page->seo,
            $page->resolvedLayout(),
            $page->publishedAt->format(DATE_ATOM),
            $page->locale,
            $page->resolvedTranslationGroupId(),
            '/' . $page->resolvedPublicPath(),
            $alternates,
        );
    }

    /** @return array{snapshotId: string, pageId: string, sourceRevision: int, title: string, slug: string, blocks: list<array{id: string, type: string, data: array<string, string>}>, seo: array{title: string, description: string, noIndex: bool, openGraphImageAssetId: ?string}, layout: array{theme: string, template: string, headerNavigationCode: ?string, footerNavigationCode: ?string}, publishedAt: string, locale: string, translationGroupId: string, url: string, alternates: list<array{locale: string, url: string}>} */
    public function toArray(): array
    {
        return [
            'snapshotId' => $this->snapshotId,
            'pageId' => $this->pageId,
            'sourceRevision' => $this->sourceRevision,
            'title' => $this->title,
            'slug' => $this->slug,
            'blocks' => $this->blocks,
            'seo' => $this->seo,
            'layout' => $this->layout,
            'publishedAt' => $this->publishedAt,
            'locale' => $this->locale,
            'translationGroupId' => $this->translationGroupId,
            'url' => $this->url,
            'alternates' => $this->alternates,
        ];
    }
}
