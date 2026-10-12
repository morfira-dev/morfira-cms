<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class Page
{
    /** @param list<PageBlock> $blocks */
    private function __construct(
        public PageId $id,
        public PageTitle $title,
        public PageSlug $slug,
        public PageStatus $status,
        public array $blocks,
        public PageSeo $seo,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function draft(PageId $id, PageTitle $title, PageSlug $slug, \DateTimeImmutable $createdAt): self
    {
        return new self($id, $title, $slug, PageStatus::Draft, [], PageSeo::defaults(), 1, $createdAt, $createdAt);
    }

    /** @param list<PageBlock> $blocks */
    public static function reconstitute(
        PageId $id,
        PageTitle $title,
        PageSlug $slug,
        PageStatus $status,
        array $blocks,
        PageSeo $seo,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $title, $slug, $status, $blocks, $seo, $version, $createdAt, $updatedAt);
    }

    /** @param list<PageBlock> $blocks */
    public function revise(PageTitle $title, PageSlug $slug, array $blocks, PageSeo $seo, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->id,
            $title,
            $slug,
            $this->status,
            $blocks,
            $seo,
            $this->version + 1,
            $this->createdAt,
            $updatedAt,
        );
    }
}
