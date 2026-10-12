<?php

declare(strict_types=1);

namespace Cms\Publishing\Infrastructure\Persistence;

use Cms\Publishing\Domain\PublicationSchedule;
use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationScheduleStatus;
use Cms\Publishing\Domain\PublicationTarget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DoctrinePublicationScheduleRepository implements PublicationScheduleRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function save(PublicationSchedule $schedule): void
    {
        $this->connection->executeStatement(<<<'SQL'
INSERT INTO publishing_schedules (
    id, target_type, target_id, source_version, scheduled_at, status,
    failure_reason, created_at, updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    source_version = VALUES(source_version),
    scheduled_at = VALUES(scheduled_at),
    status = VALUES(status),
    failure_reason = VALUES(failure_reason),
    updated_at = VALUES(updated_at)
SQL, [
            $schedule->id,
            $schedule->target->value,
            $schedule->targetId,
            $schedule->sourceVersion,
            self::date($schedule->scheduledAt),
            $schedule->status->value,
            $schedule->failureReason,
            self::date($schedule->createdAt),
            self::date($schedule->updatedAt),
        ]);
    }

    public function find(PublicationTarget $target, string $targetId): ?PublicationSchedule
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM publishing_schedules WHERE target_type = ? AND target_id = ?',
            [$target->value, $targetId],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function due(\DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM publishing_schedules WHERE status = ? AND scheduled_at <= ? ORDER BY scheduled_at ASC LIMIT ?',
            [PublicationScheduleStatus::Pending->value, self::date($now), $limit],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );

        return array_map($this->hydrate(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PublicationSchedule
    {
        return PublicationSchedule::reconstitute(
            self::string($row, 'id'),
            PublicationTarget::from(self::string($row, 'target_type')),
            self::string($row, 'target_id'),
            self::integer($row, 'source_version'),
            self::dateTime($row, 'scheduled_at'),
            PublicationScheduleStatus::from(self::string($row, 'status')),
            self::nullableString($row, 'failure_reason'),
            self::dateTime($row, 'created_at'),
            self::dateTime($row, 'updated_at'),
        );
    }

    private static function date(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $row */
    private static function dateTime(array $row, string $key): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::string($row, $key), new \DateTimeZone('UTC'));
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be a nullable string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be an integer.', $key));
        }

        return (int) $value;
    }
}
