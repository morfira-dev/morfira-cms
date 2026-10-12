<?php

declare(strict_types=1);

namespace Cms\Tests\Content;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageSlug;
use Cms\Content\Domain\PageTitle;
use Cms\Content\Domain\PageTranslationAlreadyExists;
use PHPUnit\Framework\TestCase;

final class LocalizedPageTest extends TestCase
{
    public function test_locale_and_translation_group_are_versioned_with_the_page(): void
    {
        $id = PageId::fromString('11111111-1111-4111-8111-111111111111');
        $group = '22222222-2222-4222-8222-222222222222';
        $page = Page::draft($id, new PageTitle('О проекте'), new PageSlug('o-proekte'), new \DateTimeImmutable('2026-10-13T10:00:00Z'), 'ru-RU', $group);

        self::assertSame('ru-RU', $page->locale);
        self::assertSame($group, $page->translationGroupId);
        self::assertSame('ru-RU', $page->revise($page->title, $page->slug, [], $page->seo, new \DateTimeImmutable('2026-10-13T11:00:00Z'))->locale);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Page::draft(PageId::new(), new PageTitle('Page'), new PageSlug('page'), new \DateTimeImmutable(), '../ru');
    }

    public function test_translation_group_contains_at_most_one_page_per_locale(): void
    {
        $pages = new InMemoryPageRepository();
        $group = '22222222-2222-4222-8222-222222222222';
        $create = new CreatePage($pages);
        $create('First', 'first', 'en', $group);

        $this->expectException(PageTranslationAlreadyExists::class);
        $create('Second', 'second', 'en', $group);
    }
}
