<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageVersionConflict;
use Cms\Publishing\Domain\PublicationSchedule;
use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationTarget;

final readonly class PlanPagePublication
{
    public function __construct(private PageRepository $pages, private PublicationScheduleRepository $schedules)
    {
    }

    public function __invoke(string $id, int $sourceVersion, \DateTimeImmutable $scheduledAt): PublicationScheduleView
    {
        $page = $this->pages->get(PageId::fromString($id));
        if ($page === null) {
            throw new PageNotFound();
        }
        if ($page->version !== $sourceVersion) {
            throw new PageVersionConflict();
        }

        return $this->plan(PublicationTarget::Page, $id, $sourceVersion, $scheduledAt);
    }

    private function plan(PublicationTarget $target, string $id, int $sourceVersion, \DateTimeImmutable $scheduledAt): PublicationScheduleView
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $current = $this->schedules->find($target, $id);
        $schedule = $current === null
            ? PublicationSchedule::plan(self::newUuid(), $target, $id, $sourceVersion, $scheduledAt, $now)
            : $current->reschedule($sourceVersion, $scheduledAt, $now);
        $this->schedules->save($schedule);

        return PublicationScheduleView::fromSchedule($schedule);
    }

    private static function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
