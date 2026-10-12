<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

final readonly class PublicationSchedule
{
    private function __construct(
        public string $id,
        public PublicationTarget $target,
        public string $targetId,
        public int $sourceVersion,
        public \DateTimeImmutable $scheduledAt,
        public PublicationScheduleStatus $status,
        public ?string $failureReason,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if ($sourceVersion < 1) {
            throw new \InvalidArgumentException('The scheduled source version must be positive.');
        }
    }

    public static function plan(string $id, PublicationTarget $target, string $targetId, int $sourceVersion, \DateTimeImmutable $scheduledAt, \DateTimeImmutable $now): self
    {
        if ($scheduledAt <= $now) {
            throw new \InvalidArgumentException('The publication time must be in the future.');
        }

        return new self($id, $target, $targetId, $sourceVersion, $scheduledAt, PublicationScheduleStatus::Pending, null, $now, $now);
    }

    public static function reconstitute(string $id, PublicationTarget $target, string $targetId, int $sourceVersion, \DateTimeImmutable $scheduledAt, PublicationScheduleStatus $status, ?string $failureReason, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): self
    {
        return new self($id, $target, $targetId, $sourceVersion, $scheduledAt, $status, $failureReason, $createdAt, $updatedAt);
    }

    public function reschedule(int $sourceVersion, \DateTimeImmutable $scheduledAt, \DateTimeImmutable $now): self
    {
        return self::plan($this->id, $this->target, $this->targetId, $sourceVersion, $scheduledAt, $now)
            ->withCreatedAt($this->createdAt);
    }

    public function cancel(\DateTimeImmutable $now): self
    {
        if ($this->status !== PublicationScheduleStatus::Pending) {
            throw new \DomainException('Only a pending publication schedule can be cancelled.');
        }

        return new self($this->id, $this->target, $this->targetId, $this->sourceVersion, $this->scheduledAt, PublicationScheduleStatus::Cancelled, null, $this->createdAt, $now);
    }

    public function complete(\DateTimeImmutable $now): self
    {
        return new self($this->id, $this->target, $this->targetId, $this->sourceVersion, $this->scheduledAt, PublicationScheduleStatus::Completed, null, $this->createdAt, $now);
    }

    public function fail(string $reason, \DateTimeImmutable $now): self
    {
        $boundedReason = mb_substr(trim($reason) ?: 'Scheduled publication failed.', 0, 500);

        return new self($this->id, $this->target, $this->targetId, $this->sourceVersion, $this->scheduledAt, PublicationScheduleStatus::Failed, $boundedReason, $this->createdAt, $now);
    }

    private function withCreatedAt(\DateTimeImmutable $createdAt): self
    {
        return new self($this->id, $this->target, $this->targetId, $this->sourceVersion, $this->scheduledAt, $this->status, $this->failureReason, $createdAt, $this->updatedAt);
    }
}
