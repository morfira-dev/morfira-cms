<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Application;

use Cms\Publishing\Application\ListPublishedPages\ListPublishedPages;
use Cms\Publishing\Domain\PublishedPage;
use Cms\Tests\Publishing\InMemoryPublishedPageRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListPublishedPagesTest extends TestCase
{
    #[Test]
    public function it_excludes_noindex_snapshots_from_discovery(): void
    {
        $pages = new InMemoryPublishedPageRepository();
        $pages->publish($this->page('11111111-1111-4111-8111-111111111111', 'public-page', false));
        $pages->publish($this->page('22222222-2222-4222-8222-222222222222', 'hidden-page', true));
        $pages->publish($this->page('33333333-3333-4333-8333-333333333333', '404', false));

        $indexable = (new ListPublishedPages($pages))();

        self::assertCount(1, $indexable);
        self::assertSame('public-page', $indexable[0]->slug);
    }

    private function page(string $pageId, string $slug, bool $noIndex): PublishedPage
    {
        return new PublishedPage(
            $pageId,
            $pageId,
            1,
            ucfirst(str_replace('-', ' ', $slug)),
            $slug,
            [],
            ['title' => '', 'description' => '', 'noIndex' => $noIndex, 'openGraphImageAssetId' => null],
            new \DateTimeImmutable('2026-10-10T12:00:00+00:00'),
        );
    }
}
