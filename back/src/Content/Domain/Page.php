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
        public PageLayout $layout,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public string $locale,
        public string $translationGroupId,
    ) {
        self::assertLocale($locale);
        self::assertTranslationGroupId($translationGroupId);
    }

    public static function draft(PageId $id, PageTitle $title, PageSlug $slug, \DateTimeImmutable $createdAt, string $locale = 'en', ?string $translationGroupId = null): self
    {
        return new self($id, $title, $slug, PageStatus::Draft, [], PageSeo::defaults(), PageLayout::defaults(), 1, $createdAt, $createdAt, $locale, $translationGroupId ?? $id->value);
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
        ?PageLayout $layout = null,
        string $locale = 'en',
        ?string $translationGroupId = null,
    ): self {
        return new self($id, $title, $slug, $status, $blocks, $seo, $layout ?? PageLayout::defaults(), $version, $createdAt, $updatedAt, $locale, $translationGroupId ?? $id->value);
    }

    /** @param list<PageBlock> $blocks */
    public function revise(PageTitle $title, PageSlug $slug, array $blocks, PageSeo $seo, \DateTimeImmutable $updatedAt, ?PageLayout $layout = null, ?string $locale = null, ?string $translationGroupId = null): self
    {
        return new self(
            $this->id,
            $title,
            $slug,
            $this->status,
            $blocks,
            $seo,
            $layout ?? $this->layout,
            $this->version + 1,
            $this->createdAt,
            $updatedAt,
            $locale ?? $this->locale,
            $translationGroupId ?? $this->translationGroupId,
        );
    }

    private static function assertLocale(string $locale): void
    {
        if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            throw new \InvalidArgumentException('Locale must be a normalized language tag such as en or pt-BR.');
        }
    }

    private static function assertTranslationGroupId(string $id): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Translation group identifier must be a UUID.');
        }
    }
}
