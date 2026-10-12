<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\ListPageRevisions;
use Cms\Content\Application\RestorePageRevision;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Domain\PageVersionConflict;
use Cms\Tests\Content\InMemoryPageRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageRevisionHistoryTest extends TestCase
{
    #[Test]
    public function it_lists_history_and_restores_a_snapshot_as_a_new_revision(): void
    {
        $pages = new InMemoryPageRepository();
        $created = (new CreatePage($pages))('Landing', 'landing');
        $updated = (new UpdatePage($pages, new PassThroughRichTextSanitizer()))(
            $created->id,
            'Changed',
            'changed',
            [],
            PageSeo::defaults(),
            $created->version,
        );

        $history = (new ListPageRevisions($pages))($created->id);
        self::assertSame([2, 1], array_map(static fn ($revision): int => $revision->revision, $history));

        $restored = (new RestorePageRevision($pages))($created->id, 1, $updated->version);
        self::assertSame(3, $restored->version);
        self::assertSame('Landing', $restored->title);
        self::assertSame('landing', $restored->slug);
        self::assertSame([3, 2, 1], array_map(static fn ($revision): int => $revision->revision, (new ListPageRevisions($pages))($created->id)));
    }

    #[Test]
    public function it_rejects_a_restore_based_on_a_stale_head(): void
    {
        $pages = new InMemoryPageRepository();
        $created = (new CreatePage($pages))('Landing', 'landing');
        (new UpdatePage($pages, new PassThroughRichTextSanitizer()))($created->id, 'Changed', 'changed', [], PageSeo::defaults(), 1);

        $this->expectException(PageVersionConflict::class);
        (new RestorePageRevision($pages))($created->id, 1, 1);
    }
}
