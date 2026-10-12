<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Domain\PageSlugAlreadyExists;
use Cms\Tests\Content\InMemoryPageRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreatePageTest extends TestCase
{
    #[Test]
    public function it_creates_a_draft_page(): void
    {
        $pages = new InMemoryPageRepository();
        $createPage = new CreatePage($pages);

        $page = $createPage('About us', 'about-us');

        self::assertSame('About us', $page->title);
        self::assertSame('about-us', $page->slug);
        self::assertSame('draft', $page->status);
        self::assertCount(1, $pages->all());
    }

    #[Test]
    public function it_rejects_a_duplicate_slug(): void
    {
        $pages = new InMemoryPageRepository();
        $createPage = new CreatePage($pages);
        $createPage('First', 'same-slug');

        $this->expectException(PageSlugAlreadyExists::class);

        $createPage('Second', 'same-slug');
    }

    #[Test]
    public function it_rejects_an_invalid_slug(): void
    {
        $createPage = new CreatePage(new InMemoryPageRepository());

        $this->expectException(\InvalidArgumentException::class);

        $createPage('About us', 'About Us');
    }
}
