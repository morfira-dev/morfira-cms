<?php

declare(strict_types=1);

namespace Cms\Extensions\Application;

final readonly class PagePublishedContext
{
    public function __construct(
        public string $pageId,
        public string $snapshotId,
        public int $sourceRevision,
        public string $slug,
    ) {
    }
}
