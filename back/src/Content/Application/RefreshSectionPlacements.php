<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageSectionLinks;
use Cms\Content\Domain\PageVersionConflict;
use Cms\Content\Domain\ReusableSection;
use Cms\Content\Domain\ReusableSectionConflict;
use Cms\Content\Domain\ReusableSectionRepository;

/** @phpstan-import-type SectionLink from PageSectionLinks */
final readonly class RefreshSectionPlacements
{
    public function __construct(
        private PageRepository $pages,
        private ReusableSectionRepository $sections,
        private RichTextSanitizer $sanitizer,
    ) {
    }

    /**
     * @param list<array{pageId: PageId, version: int}> $targets
     * @return list<array{pageId: string, status: 'updated'|'current'|'conflict'|'missing'|'unlinked', version?: int}>
     */
    public function __invoke(string $sectionId, int $sectionVersion, array $targets): array
    {
        $section = $this->sections->get($sectionId) ?? throw new \OutOfBoundsException('Reusable section not found.');
        if ($section->version !== $sectionVersion) {
            throw new ReusableSectionConflict();
        }

        $sourceBlocks = array_map($this->sanitizeBlock(...), $section->blocks);
        $results = [];
        foreach ($targets as $target) {
            $id = $target['pageId'];
            $page = $this->pages->get($id);
            if ($page === null) {
                $results[] = ['pageId' => $id->value, 'status' => 'missing'];
                continue;
            }

            $result = ['pageId' => $id->value, 'version' => $page->version];
            if ($page->version !== $target['version']) {
                $results[] = [...$result, 'status' => 'conflict'];
                continue;
            }
            $links = array_filter($page->sectionLinks, static fn (array $link): bool => $link['sectionId'] === $sectionId);
            if ($links === []) {
                $results[] = [...$result, 'status' => 'unlinked'];
                continue;
            }
            if ($section->locale !== null && $section->locale !== $page->locale) {
                $results[] = [...$result, 'status' => 'conflict'];
                continue;
            }
            $outdated = array_filter($links, static fn (array $link): bool => $link['version'] < $sectionVersion);
            if ($outdated === []) {
                $results[] = [...$result, 'status' => 'current'];
                continue;
            }

            $updated = $this->materialize($page, $section, $outdated, $sourceBlocks);
            try {
                $this->pages->save($updated, $page->version);
                $results[] = ['pageId' => $id->value, 'version' => $updated->version, 'status' => 'updated'];
            } catch (PageVersionConflict) {
                $latest = $this->pages->get($id);
                $results[] = $latest === null
                    ? ['pageId' => $id->value, 'status' => 'missing']
                    : ['pageId' => $id->value, 'version' => $latest->version, 'status' => 'conflict'];
            }
        }

        return $results;
    }

    /**
     * @param array<int, SectionLink> $outdated
     * @param list<PageBlock> $sourceBlocks
     */
    private function materialize(Page $page, ReusableSection $section, array $outdated, array $sourceBlocks): Page
    {
        $placements = [];
        foreach ($outdated as $index => $link) {
            foreach ($link['blockIds'] as $blockId) {
                $placements[$blockId] = $index;
            }
        }

        $links = $page->sectionLinks;
        $replaced = [];
        $blocks = [];
        foreach ($page->blocks as $block) {
            $index = $placements[$block->id] ?? null;
            if ($index === null) {
                $blocks[] = $block;
                continue;
            }
            if (isset($replaced[$index])) {
                continue;
            }
            $copies = array_map(static fn (PageBlock $source): PageBlock => PageBlock::fromArray([...$source->toArray(), 'id' => PageId::new()->value]), $sourceBlocks);
            $blockIds = array_map(static fn (PageBlock $copy): string => $copy->id, $copies);
            $links[$index] = ['sectionId' => $section->id, 'version' => $section->version, 'blockIds' => $blockIds];
            array_push($blocks, ...$copies);
            $replaced[$index] = true;
        }

        return $page->revise(
            $page->title,
            $page->slug,
            $blocks,
            $page->seo,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            sectionLinks: $links,
        );
    }

    private function sanitizeBlock(PageBlock $block): PageBlock
    {
        $data = $block->data;
        if ($block->type === 'richText' && isset($data['content'])) {
            $data['content'] = $this->sanitizer->sanitize($data['content']);
        }

        return PageBlock::fromArray([...$block->toArray(), 'data' => $data]);
    }
}
