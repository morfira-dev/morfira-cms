<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

/** @phpstan-type SectionLink array{sectionId: string, version: int, blockIds: non-empty-list<string>} */
final class PageSectionLinks
{
    /**
     * @param list<mixed> $links
     * @param list<PageBlock> $blocks
     * @return list<SectionLink>
     */
    public static function validate(array $links, array $blocks): array
    {
        $available = self::uniqueBlockIds($blocks);
        $result = [];
        foreach ($links as $link) {
            if (!is_array($link) || count($link) !== 3
                || !is_string($sectionId = $link['sectionId'] ?? null)
                || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $sectionId) !== 1
                || !is_int($version = $link['version'] ?? null) || $version < 1
                || !is_array($ids = $link['blockIds'] ?? null) || !array_is_list($ids) || $ids === []) {
                throw new \InvalidArgumentException('Invalid linked section placement.');
            }

            $blockIds = [];
            foreach ($ids as $id) {
                if (!is_string($id) || !isset($available[$id])) {
                    throw new \InvalidArgumentException('A section block must identify one block and belong to only one placement.');
                }
                unset($available[$id]);
                $blockIds[] = $id;
            }
            $result[] = ['sectionId' => $sectionId, 'version' => $version, 'blockIds' => $blockIds];
        }

        return $result;
    }

    /**
     * @param list<SectionLink> $links
     * @param list<PageBlock> $blocks
     * @return list<SectionLink>
     */
    public static function retain(array $links, array $blocks): array
    {
        $ids = array_keys(self::uniqueBlockIds($blocks));

        return array_values(array_filter($links, static fn (array $link): bool => array_diff($link['blockIds'], $ids) === []));
    }

    /** @param list<PageBlock> $blocks
     *  @return array<string, 1>
     */
    private static function uniqueBlockIds(array $blocks): array
    {
        $counts = array_count_values(array_map(static fn (PageBlock $block): string => $block->id, $blocks));

        return array_filter($counts, static fn (int $count): bool => $count === 1);
    }
}
