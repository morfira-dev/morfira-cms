<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Application;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\SanitizeEntryValues;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\PageSeo;
use Cms\Publishing\Application\CancelPublicationSchedule;
use Cms\Publishing\Application\PlanEntryPublication;
use Cms\Publishing\Application\PlanPagePublication;
use Cms\Publishing\Application\PublishEntry;
use Cms\Publishing\Application\PublishPage\PublishPage;
use Cms\Publishing\Application\RunScheduledPublications;
use Cms\Publishing\Domain\PublicationScheduleStatus;
use Cms\Publishing\Domain\PublicationSchedule;
use Cms\Publishing\Domain\PublicationTarget;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Content\InMemoryPageRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use Cms\Tests\Publishing\InMemoryPublicationScheduleRepository;
use Cms\Tests\Publishing\InMemoryPublishedEntryRepository;
use Cms\Tests\Publishing\InMemoryPublishedPageRepository;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScheduledPublishingTest extends TestCase
{
    #[Test]
    public function it_plans_and_processes_due_pages_and_entries(): void
    {
        [$pages, $entries, $schedules, $publishPage, $publishEntry, $pageId, $entryId] = $this->fixture();
        $due = new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC'));
        (new PlanPagePublication($pages, $schedules))($pageId, 1, $due);
        (new PlanEntryPublication($entries, $schedules))($entryId, 1, $due);
        $this->makeDue($schedules, PublicationTarget::Page, $pageId);
        $this->makeDue($schedules, PublicationTarget::Entry, $entryId);

        $result = (new RunScheduledPublications($schedules, $publishPage, $publishEntry))(100);

        self::assertSame(['processed' => 2, 'completed' => 2, 'failed' => 0], $result);
        self::assertSame(PublicationScheduleStatus::Completed, $schedules->find(PublicationTarget::Page, $pageId)?->status);
        self::assertSame(PublicationScheduleStatus::Completed, $schedules->find(PublicationTarget::Entry, $entryId)?->status);
    }

    #[Test]
    public function it_fails_instead_of_publishing_a_newer_page_revision(): void
    {
        [$pages, , $schedules, $publishPage, $publishEntry, $pageId] = $this->fixture();
        $due = new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC'));
        (new PlanPagePublication($pages, $schedules))($pageId, 1, $due);
        $this->makeDue($schedules, PublicationTarget::Page, $pageId);
        (new UpdatePage($pages, new PassThroughRichTextSanitizer()))($pageId, 'Changed', 'landing', [], PageSeo::defaults(), 1);

        $result = (new RunScheduledPublications($schedules, $publishPage, $publishEntry))(100);

        self::assertSame(1, $result['failed']);
        self::assertSame(PublicationScheduleStatus::Failed, $schedules->find(PublicationTarget::Page, $pageId)?->status);
    }

    #[Test]
    public function cancellation_prevents_processing(): void
    {
        [$pages, , $schedules, $publishPage, $publishEntry, $pageId] = $this->fixture();
        (new PlanPagePublication($pages, $schedules))($pageId, 1, new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC')));
        $this->makeDue($schedules, PublicationTarget::Page, $pageId);
        (new CancelPublicationSchedule($schedules))(PublicationTarget::Page, $pageId);

        $result = (new RunScheduledPublications($schedules, $publishPage, $publishEntry))(100);

        self::assertSame(0, $result['processed']);
        self::assertSame(PublicationScheduleStatus::Cancelled, $schedules->find(PublicationTarget::Page, $pageId)?->status);
    }

    /** @return array{InMemoryPageRepository, InMemoryEntryRepository, InMemoryPublicationScheduleRepository, PublishPage, PublishEntry, string, string} */
    private function fixture(): array
    {
        $pages = new InMemoryPageRepository();
        $page = (new CreatePage($pages))('Landing', 'landing');
        $types = new InMemoryContentTypeRepository();
        $type = (new CreateContentType($types))('Article', 'articles', []);
        $definitions = new GetContentTypeDefinition($types);
        $validator = new ValidateEntryValues($definitions);
        $sanitizer = new SanitizeEntryValues($definitions, new PassThroughRichTextSanitizer());
        $entries = new InMemoryEntryRepository();
        $entry = (new CreateEntry($entries, $validator, $sanitizer))($type->id, 'First', 'first', []);
        $schedules = new InMemoryPublicationScheduleRepository();

        return [
            $pages,
            $entries,
            $schedules,
            new PublishPage($pages, new InMemoryPublishedPageRepository()),
            new PublishEntry($entries, new InMemoryPublishedEntryRepository(), $definitions, $validator, $sanitizer),
            $page->id,
            $entry->id,
        ];
    }

    private function makeDue(InMemoryPublicationScheduleRepository $schedules, PublicationTarget $target, string $targetId): void
    {
        $schedule = $schedules->find($target, $targetId);
        self::assertNotNull($schedule);
        $schedules->save(PublicationSchedule::reconstitute(
            $schedule->id,
            $schedule->target,
            $schedule->targetId,
            $schedule->sourceVersion,
            new \DateTimeImmutable('-1 second', new \DateTimeZone('UTC')),
            $schedule->status,
            $schedule->failureReason,
            $schedule->createdAt,
            $schedule->updatedAt,
        ));
    }
}
