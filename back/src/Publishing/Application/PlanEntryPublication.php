<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Publishing\Domain\PublicationSchedule;
use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationTarget;

final readonly class PlanEntryPublication
{
    public function __construct(private EntryRepository $entries, private PublicationScheduleRepository $schedules)
    {
    }

    public function __invoke(string $id, int $sourceVersion, \DateTimeImmutable $scheduledAt): PublicationScheduleView
    {
        $entry = $this->entries->get(EntryId::fromString($id));
        if ($entry === null) {
            throw new EntryNotFound();
        }
        if ($entry->version !== $sourceVersion) {
            throw new EntryVersionConflict();
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $current = $this->schedules->find(PublicationTarget::Entry, $id);
        $schedule = $current === null
            ? PublicationSchedule::plan(self::newUuid(), PublicationTarget::Entry, $id, $sourceVersion, $scheduledAt, $now)
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
