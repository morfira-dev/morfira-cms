<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;
use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationScheduleStatus;
use Cms\Publishing\Domain\PublicationTarget;
use Cms\Publishing\Domain\PublishedEntryRepository;

final readonly class UnpublishEntry
{
    public function __construct(
        private EntryRepository $entries,
        private PublishedEntryRepository $published,
        private PublicationScheduleRepository $schedules,
    ) {
    }

    public function __invoke(string $entryId): void
    {
        $id = EntryId::fromString($entryId);
        if ($this->entries->get($id) === null) {
            throw new EntryNotFound();
        }

        $this->published->unpublish($id->value);
        $schedule = $this->schedules->find(PublicationTarget::Entry, $id->value);
        if ($schedule?->status === PublicationScheduleStatus::Pending) {
            $this->schedules->save($schedule->cancel(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))));
        }
    }
}
