<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

interface PublicationScheduleRepository
{
    public function save(PublicationSchedule $schedule): void;

    public function find(PublicationTarget $target, string $targetId): ?PublicationSchedule;

    /** @return list<PublicationSchedule> */
    public function due(\DateTimeImmutable $now, int $limit): array;
}
