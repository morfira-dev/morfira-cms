<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\RefreshSectionPlacements;
use Cms\Content\Application\RichTextSanitizer;
use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageSlug;
use Cms\Content\Domain\PageTitle;
use Cms\Content\Domain\ReusableSection;
use Cms\Content\Domain\ReusableSectionConflict;
use Cms\Content\Domain\ReusableSectionRepository;
use Cms\Tests\Content\InMemoryPageRepository;
use PHPUnit\Framework\TestCase;

final class RefreshSectionPlacementsTest extends TestCase
{
    public function testBatchRefreshesEveryOutdatedPlacementAndPreservesOtherContent(): void
    {
        $clock = new \DateTimeImmutable('2026-11-25T00:00:00Z');
        $sectionId = PageId::new()->value;
        $linkedBlock = self::hero('Old linked block');
        $unrelatedBlock = self::hero('Keep this block');
        $secondLink = self::hero('Another placement');
        $sourceBlock = PageBlock::fromArray(['id' => PageId::new()->value, 'type' => 'richText', 'data' => ['content' => '<p>Source paragraph</p>']]);
        $section = new ReusableSection($sectionId, 'Shared section', [$sourceBlock], 2, $clock, $clock);
        $pages = new InMemoryPageRepository();
        $page = Page::draft(PageId::new(), new PageTitle('Landing'), new PageSlug('landing'), $clock);
        $links = [
            ['sectionId' => $sectionId, 'version' => 1, 'blockIds' => [$linkedBlock->id]],
            ['sectionId' => $sectionId, 'version' => 1, 'blockIds' => [$secondLink->id]],
        ];
        $pages->add($page);
        $linked = $page->revise($page->title, $page->slug, [$linkedBlock, $unrelatedBlock, $secondLink], $page->seo, $clock, sectionLinks: $links);
        $pages->save($linked, 1);
        $other = Page::draft(PageId::new(), new PageTitle('Elsewhere'), new PageSlug('elsewhere'), $clock);
        $pages->add($other);
        $concurrent = Page::draft(PageId::new(), new PageTitle('Concurrent edit'), new PageSlug('concurrent-edit'), $clock);
        $pages->add($concurrent);

        $sections = $this->createStub(ReusableSectionRepository::class);
        $sections->method('get')->willReturn($section);
        $sanitizer = $this->createMock(RichTextSanitizer::class);
        $sanitizer->expects($this->once())->method('sanitize')->with('<p>Source paragraph</p>')->willReturn('<p>Clean paragraph</p>');
        $refresh = new RefreshSectionPlacements($pages, $sections, $sanitizer);

        try {
            $refresh($sectionId, 1, [['pageId' => $linked->id, 'version' => 2]]);
            self::fail('A stale section version must reject the batch.');
        } catch (ReusableSectionConflict) {
            self::assertSame($linked, $pages->get($linked->id));
        }

        $missing = PageId::new();
        $results = $refresh($sectionId, 2, [
            ['pageId' => $concurrent->id, 'version' => 2],
            ['pageId' => $linked->id, 'version' => 2],
            ['pageId' => $other->id, 'version' => 1],
            ['pageId' => $missing, 'version' => 1],
        ]);
        self::assertSame(['conflict', 'updated', 'unlinked', 'missing'], array_column($results, 'status'));
        $updated = $pages->get($linked->id);
        self::assertSame(3, $updated->version);
        self::assertSame($unrelatedBlock, $updated->blocks[1]);
        self::assertSame('<p>Clean paragraph</p>', $updated->blocks[0]->data['content']);
        self::assertSame('<p>Clean paragraph</p>', $updated->blocks[2]->data['content']);
        self::assertNotSame($updated->blocks[0]->id, $updated->blocks[2]->id);
        self::assertSame([
            ['sectionId' => $sectionId, 'version' => 2, 'blockIds' => [$updated->blocks[0]->id]],
            ['sectionId' => $sectionId, 'version' => 2, 'blockIds' => [$updated->blocks[2]->id]],
        ], $updated->sectionLinks);
        self::assertSame($links, $pages->revision($linked->id, 2)?->sectionLinks);
        self::assertSame($other, $pages->get($other->id));
    }

    private static function hero(string $heading): PageBlock
    {
        return PageBlock::fromArray(['id' => PageId::new()->value, 'type' => 'hero', 'data' => ['heading' => $heading]]);
    }
}
