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
    ) {
    }
}
