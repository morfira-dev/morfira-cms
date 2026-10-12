<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationTarget;

final readonly class CancelPublicationSchedule
{
    public function __construct(private PublicationScheduleRepository $schedules)
    {
    }

    public function __invoke(PublicationTarget $target, string $targetId): ?PublicationScheduleView
    {
        $schedule = $this->schedules->find($target, $targetId);
        if ($schedule === null) {
            return null;
        }
        $cancelled = $schedule->cancel(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->schedules->save($cancelled);

        return PublicationScheduleView::fromSchedule($cancelled);
    }
}
