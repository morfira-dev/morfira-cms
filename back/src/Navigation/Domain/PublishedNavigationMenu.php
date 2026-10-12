<?php

declare(strict_types=1);

namespace Cms\Navigation\Domain;

final readonly class PublishedNavigationMenu
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public string $snapshotId,
        public string $menuId,
        public int $sourceVersion,
        public string $name,
        public string $code,
        public array $items,
        public \DateTimeImmutable $publishedAt,
    ) {
    }
}
