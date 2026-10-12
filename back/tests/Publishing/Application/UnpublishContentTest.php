<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Application;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\PageSeo;
use Cms\Publishing\Application\PlanEntryPublication;
use Cms\Publishing\Application\PlanPagePublication;
use Cms\Publishing\Application\PublishEntry;
use Cms\Publishing\Application\PublishPage\PublishPage;
use Cms\Publishing\Application\UnpublishEntry;
use Cms\Publishing\Application\UnpublishPage;
use Cms\Publishing\Domain\PublicationScheduleStatus;
use Cms\Publishing\Domain\PublicationTarget;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Content\InMemoryPageRepository;
use Cms\Tests\Publishing\InMemoryPublicationScheduleRepository;
use Cms\Tests\Publishing\InMemoryPublishedEntryRepository;
use Cms\Tests\Publishing\InMemoryPublishedPageRepository;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnpublishContentTest extends TestCase
{
    #[Test]
    public function page_unpublish_removes_route_and_redirects_and_cancels_pending_schedule(): void
    {
        $drafts = new InMemoryPageRepository();
        $page = (new CreatePage($drafts))('Landing', 'landing');
        $published = new InMemoryPublishedPageRepository();
        $publish = new PublishPage($drafts, $published);
        $publish($page->id, 1);
        $updated = (new UpdatePage($drafts))($page->id, 'Landing', 'home', [], PageSeo::defaults(), 1);
        $publish($page->id, $updated->version);
        $schedules = new InMemoryPublicationScheduleRepository();
        (new PlanPagePublication($drafts, $schedules))($page->id, $updated->version, new \DateTimeImmutable('+1 hour'));

        $unpublish = new UnpublishPage($drafts, $published, $schedules);
        $unpublish($page->id);
        $unpublish($page->id);

        self::assertNull($published->findBySlug('home'));
        self::assertNull($published->findRedirectTargetBySlug('landing'));
        self::assertSame(PublicationScheduleStatus::Cancelled, $schedules->find(PublicationTarget::Page, $page->id)?->status);
    }

    #[Test]
    public function entry_unpublish_removes_route_but_preserves_snapshot_and_cancels_schedule(): void
    {
        $types = new InMemoryContentTypeRepository();
        $type = (new CreateContentType($types))('Article', 'articles', []);
        $definitions = new GetContentTypeDefinition($types);
        $validator = new ValidateEntryValues($definitions);
        $drafts = new InMemoryEntryRepository();
        $entry = (new CreateEntry($drafts, $validator))($type->id, 'First', 'first', []);
        $published = new InMemoryPublishedEntryRepository();
        (new PublishEntry($drafts, $published, $definitions, $validator))($entry->id, 1);
        $schedules = new InMemoryPublicationScheduleRepository();
        (new PlanEntryPublication($drafts, $schedules))($entry->id, 1, new \DateTimeImmutable('+1 hour'));

        $unpublish = new UnpublishEntry($drafts, $published, $schedules);
        $unpublish($entry->id);
        $unpublish($entry->id);

        self::assertNull($published->find('articles', 'first'));
        self::assertNotNull($published->findByVersion($entry->id, 1));
        self::assertSame(PublicationScheduleStatus::Cancelled, $schedules->find(PublicationTarget::Entry, $entry->id)?->status);
    }
}
