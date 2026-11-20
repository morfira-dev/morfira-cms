<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressImportRunHistory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DoctrineWordPressImportRunHistory implements WordPressImportRunHistory
{
    public function __construct(private Connection $connection)
    {
    }

    public function record(array $report): void
    {
        $utc = new \DateTimeZone('UTC');
        $this->connection->insert('content_wordpress_import_runs', [
            'id' => self::uuid(),
            'source_key' => $report['source'],
            'scope' => $report['scope'] ?? 'all',
            'completed_at' => new \DateTimeImmutable('now', $utc)->format('Y-m-d H:i:s.u'),
            'counts_json' => json_encode($report['counts'], JSON_THROW_ON_ERROR),
            'report_json' => json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function latest(int $offset, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, source_key, scope, completed_at, counts_json FROM content_wordpress_import_runs ORDER BY completed_at DESC, id DESC LIMIT ? OFFSET ?',
            [$limit, $offset],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );
        $runs = [];
        $utc = new \DateTimeZone('UTC');
        foreach ($rows as $row) {
            $id = $row['id'];
            $source = $row['source_key'];
            $scope = $row['scope'];
            $completedAt = $row['completed_at'];
            if (!is_string($id) || !is_string($source) || !in_array($scope, ['all', 'selection'], true) || !is_string($completedAt)) {
                throw new \UnexpectedValueException('Invalid WordPress import run summary.');
            }
            $runs[] = [
                'id' => $id,
                'source' => $source,
                'scope' => $scope,
                'completedAt' => new \DateTimeImmutable($completedAt, $utc)->format(DATE_RFC3339_EXTENDED),
                'counts' => self::decodeObject($row['counts_json']),
            ];
        }

        return $runs;
    }

    public function report(string $id): ?\stdClass
    {
        $json = $this->connection->fetchOne('SELECT report_json FROM content_wordpress_import_runs WHERE id = ?', [$id]);

        return $json === false ? null : self::decodeObject($json);
    }

    private static function decodeObject(mixed $json): \stdClass
    {
        $object = is_string($json) ? json_decode($json, flags: JSON_THROW_ON_ERROR) : null;
        if (!$object instanceof \stdClass) {
            throw new \UnexpectedValueException('Invalid WordPress import run JSON.');
        }

        return $object;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
