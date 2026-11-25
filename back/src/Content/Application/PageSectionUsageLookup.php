<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\PageSectionLinks;

/**
 * @phpstan-import-type SectionLink from PageSectionLinks
 * @phpstan-type Usage array{pageId: string, title: string, slug: string, locale: string, version: int, placements: non-empty-list<SectionLink>}
 */
interface PageSectionUsageLookup
{
    /** @return array{items: list<Usage>, total: int} */
    public function forSection(string $sectionId, int $limit, int $offset): array;
}
