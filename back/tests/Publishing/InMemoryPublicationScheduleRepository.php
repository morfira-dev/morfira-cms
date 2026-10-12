<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing;

use Cms\Publishing\Domain\PublicationSchedule;
use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationScheduleStatus;
use Cms\Publishing\Domain\PublicationTarget;

final class InMemoryPublicationScheduleRepository implements PublicationScheduleRepository
{
    /** @var array<string, PublicationSchedule> */
    private array $items = [];

    public function save(PublicationSchedule $schedule): void
    {
        $this->items[$this->key($schedule->target, $schedule->targetId)] = $schedule;
    }

    public function find(PublicationTarget $target, string $targetId): ?PublicationSchedule
    {
        return $this->items[$this->key($target, $targetId)] ?? null;
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $due = array_values(array_filter(
            $this->items,
            static fn (PublicationSchedule $schedule): bool => $schedule->status === PublicationScheduleStatus::Pending && $schedule->scheduledAt <= $now,
        ));
        usort($due, static fn (PublicationSchedule $left, PublicationSchedule $right): int => $left->scheduledAt <=> $right->scheduledAt);

        return array_slice($due, 0, $limit);
    }

    private function key(PublicationTarget $target, string $targetId): string
    {
        return $target->value . ':' . $targetId;
    }
}
