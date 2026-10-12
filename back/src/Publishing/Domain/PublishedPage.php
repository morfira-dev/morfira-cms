<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

final readonly class PublishedPage
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
        public \DateTimeImmutable $publishedAt,
        /** @var array{theme: string, template: string, headerNavigationCode: ?string, footerNavigationCode: ?string}|null */
        public ?array $layout = null,
        public string $locale = 'en',
        public ?string $translationGroupId = null,
        public ?string $publicPath = null,
    ) {
    }

    /** @return array{theme: string, template: string, headerNavigationCode: ?string, footerNavigationCode: ?string} */
    public function resolvedLayout(): array
    {
        return $this->layout ?? ['theme' => 'morfira', 'template' => 'standard', 'headerNavigationCode' => null, 'footerNavigationCode' => null];
    }

    public function resolvedTranslationGroupId(): string
    {
        return $this->translationGroupId ?? $this->pageId;
    }

    public function resolvedPublicPath(): string
    {
        return $this->publicPath ?? $this->slug;
    }
}
