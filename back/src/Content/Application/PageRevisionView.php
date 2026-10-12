<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\PageRevision;

final readonly class PageRevisionView
{
    public function __construct(
        public int $revision,
        public string $title,
        public string $slug,
        /** @var list<array{id: string, type: string, data: array<string, string>}> */
        public array $blocks,
        /** @var array{title: string, description: string, noIndex: bool, openGraphImageAssetId: ?string} */
        public array $seo,
        public string $createdAt,
    ) {
    }

    public static function fromRevision(PageRevision $revision): self
    {
        return new self(
            $revision->revision,
            $revision->title->value,
            $revision->slug->value,
            array_map(static fn ($block): array => $block->toArray(), $revision->blocks),
            $revision->seo->toArray(),
            $revision->createdAt->format(DATE_ATOM),
        );
    }

    /** @return array{revision: int, title: string, slug: string, blocks: list<array{id: string, type: string, data: array<string, string>}>, seo: array{title: string, description: string, noIndex: bool, openGraphImageAssetId: ?string}, createdAt: string} */
    public function toArray(): array
    {
        return ['revision' => $this->revision, 'title' => $this->title, 'slug' => $this->slug, 'blocks' => $this->blocks, 'seo' => $this->seo, 'createdAt' => $this->createdAt];
    }
}
