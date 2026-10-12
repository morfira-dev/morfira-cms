<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\PublicationSchedule;

final readonly class PublicationScheduleView
{
    private function __construct(
        public string $id,
        public string $targetType,
        public string $targetId,
        public int $sourceVersion,
        public string $scheduledAt,
        public string $status,
        public ?string $failureReason,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromSchedule(PublicationSchedule $schedule): self
    {
        return new self($schedule->id, $schedule->target->value, $schedule->targetId, $schedule->sourceVersion, $schedule->scheduledAt->format(DATE_RFC3339_EXTENDED), $schedule->status->value, $schedule->failureReason, $schedule->createdAt->format(DATE_RFC3339_EXTENDED), $schedule->updatedAt->format(DATE_RFC3339_EXTENDED));
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'sourceVersion' => $this->sourceVersion,
            'scheduledAt' => $this->scheduledAt,
            'status' => $this->status,
            'failureReason' => $this->failureReason,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
