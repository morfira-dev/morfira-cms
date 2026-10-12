<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\Page;

final readonly class PageView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $status,
        /** @var list<array{id: string, type: string, data: array<string, string>}> */
        public array $blocks,
        /** @var array{title: string, description: string, noIndex: bool, openGraphImageAssetId: ?string} */
        public array $seo,
        /** @var array{theme: string, template: string, headerNavigationCode: ?string, footerNavigationCode: ?string} */
        public array $layout,
        public int $version,
        public string $createdAt,
        public string $updatedAt,
        public string $locale,
        public string $translationGroupId,
    ) {
    }

    public static function fromPage(Page $page): self
    {
        return new self(
            $page->id->value,
            $page->title->value,
            $page->slug->value,
            $page->status->value,
            array_map(static fn ($block): array => $block->toArray(), $page->blocks),
            $page->seo->toArray(),
            $page->layout->toArray(),
            $page->version,
            $page->createdAt->format(DATE_ATOM),
            $page->updatedAt->format(DATE_ATOM),
            $page->locale,
            $page->translationGroupId,
        );
    }

    /** @return array{id: string, title: string, slug: string, status: string, blocks: list<array{id: string, type: string, data: array<string, string>}>, seo: array{title: string, description: string, noIndex: bool, openGraphImageAssetId: ?string}, layout: array{theme: string, template: string, headerNavigationCode: ?string, footerNavigationCode: ?string}, version: int, createdAt: string, updatedAt: string, locale: string, translationGroupId: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'blocks' => $this->blocks,
            'seo' => $this->seo,
            'layout' => $this->layout,
            'version' => $this->version,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'locale' => $this->locale,
            'translationGroupId' => $this->translationGroupId,
        ];
    }
}
