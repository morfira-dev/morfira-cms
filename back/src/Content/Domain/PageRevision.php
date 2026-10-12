<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class PageRevision
{
    /** @param list<PageBlock> $blocks */
    public function __construct(
        public int $revision,
        public PageTitle $title,
        public PageSlug $slug,
        public array $blocks,
        public PageSeo $seo,
        public \DateTimeImmutable $createdAt,
    ) {
        if ($revision < 1) {
            throw new \InvalidArgumentException('Page revision number must be positive.');
        }
    }
}
