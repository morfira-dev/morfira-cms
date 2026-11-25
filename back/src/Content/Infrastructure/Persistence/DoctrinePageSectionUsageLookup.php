<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Persistence;

use Cms\Content\Application\PageSectionUsageLookup;
use Cms\Content\Domain\PageSectionLinks;
use Cms\Content\Domain\PageStatus;
use Doctrine\DBAL\Connection;

/** @phpstan-import-type SectionLink from PageSectionLinks */
final readonly class DoctrinePageSectionUsageLookup implements PageSectionUsageLookup
{
    public function __construct(private Connection $connection)
    {
    }

    public function forSection(string $sectionId, int $limit, int $offset): array
    {
        $rows = $this->connection->iterateAssociative(
            'SELECT id, title, slug, locale, version, section_links FROM content_pages WHERE status = ? AND section_links IS NOT NULL ORDER BY title ASC, id ASC',
            [PageStatus::Draft->value],
        );

        $total = 0;
        $items = [];
        foreach ($rows as $row) {
            if (!is_string($row['section_links'])) {
                throw new \UnexpectedValueException('Draft section links must be stored as JSON.');
            }
            $links = json_decode($row['section_links'], true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($links) || !array_is_list($links)) {
                throw new \UnexpectedValueException('Draft section links must be a JSON list.');
            }
            /** @var list<SectionLink> $links */
            $placements = array_values(array_filter($links, static fn (array $link): bool => $link['sectionId'] === $sectionId));
            if ($placements === []) {
                continue;
            }

            ++$total;
            if ($total <= $offset || count($items) >= $limit) {
                continue;
            }

            $version = $row['version'];
            if (!is_string($row['id']) || !is_string($row['title']) || !is_string($row['slug']) || !is_string($row['locale'])
                || !(is_int($version) || (is_string($version) && ctype_digit($version)))) {
                throw new \UnexpectedValueException('Draft section usage metadata is invalid.');
            }

            $items[] = [
                'pageId' => $row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'locale' => $row['locale'],
                'version' => (int) $version,
                'placements' => $placements,
            ];
        }

        return ['items' => $items, 'total' => $total];
    }
}
