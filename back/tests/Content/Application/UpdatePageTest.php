<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\PageVersionConflict;
use Cms\Content\Domain\PageSeo;
use Cms\Tests\Content\InMemoryPageRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdatePageTest extends TestCase
{
    #[Test]
    public function it_saves_valid_blocks_as_a_new_revision(): void
    {
        $pages = new InMemoryPageRepository();
        $created = (new CreatePage($pages))('Landing', 'landing');

        $updated = (new UpdatePage($pages))(
            $created->id,
            'Landing page',
            'landing',
            [
                ['id' => 'cf49b538-07ee-4b53-a6a3-6b25cbfa22ae', 'type' => 'hero', 'data' => ['heading' => 'Hello', 'text' => 'A better CMS']],
                ['id' => '6bb4ff18-266f-44a7-a3d0-1b4d84ac0ac8', 'type' => 'richText', 'data' => ['content' => 'Useful content.']],
            ],
            new PageSeo('Landing SEO', 'A useful landing page.', false, null),
            $created->version,
        );

        self::assertSame(2, $updated->version);
        self::assertSame('Landing page', $updated->title);
        self::assertCount(2, $updated->blocks);
        self::assertSame('Landing SEO', $updated->seo['title']);
    }

    #[Test]
    public function it_rejects_a_stale_revision(): void
    {
        $pages = new InMemoryPageRepository();
        $created = (new CreatePage($pages))('Landing', 'landing');
        $update = new UpdatePage($pages);
        $update($created->id, 'Changed', 'landing', [], PageSeo::defaults(), 1);

        $this->expectException(PageVersionConflict::class);

        $update($created->id, 'Stale edit', 'landing', [], PageSeo::defaults(), 1);
    }
}
