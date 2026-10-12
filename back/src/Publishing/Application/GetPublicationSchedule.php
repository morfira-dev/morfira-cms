<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationTarget;

final readonly class GetPublicationSchedule
{
    public function __construct(private PublicationScheduleRepository $schedules)
    {
    }

    public function __invoke(PublicationTarget $target, string $targetId): ?PublicationScheduleView
    {
        $schedule = $this->schedules->find($target, $targetId);

        return $schedule === null ? null : PublicationScheduleView::fromSchedule($schedule);
    }
}
