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
            'input_format' => $report['input'],
            'scope' => $report['scope'] ?? 'all',
            'completed_at' => new \DateTimeImmutable('now', $utc)->format('Y-m-d H:i:s.u'),
            'counts_json' => json_encode($report['counts'], JSON_THROW_ON_ERROR),
            'report_json' => json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function latest(int $offset, int $limit, ?string $source = null, ?string $input = null, ?string $scope = null): array
    {
        $clauses = [];
        $values = [];
        $types = [];
        foreach (['source_key' => $source, 'input_format' => $input, 'scope' => $scope] as $column => $value) {
            if ($value !== null) {
                $clauses[] = $column.' = ?';
                $values[] = $value;
                $types[] = ParameterType::STRING;
            }
        }
        $where = $clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, source_key, input_format, scope, completed_at, counts_json FROM content_wordpress_import_runs'.$where.' ORDER BY completed_at DESC, id DESC LIMIT ? OFFSET ?',
            [...$values, $limit, $offset],
            [...$types, ParameterType::INTEGER, ParameterType::INTEGER],
        );
        $runs = [];
        $utc = new \DateTimeZone('UTC');
        foreach ($rows as $row) {
            $id = $row['id'];
            $runSource = $row['source_key'];
            $runInput = $row['input_format'];
            $runScope = $row['scope'];
            $completedAt = $row['completed_at'];
            if (!is_string($id) || !is_string($runSource) || !in_array($runInput, ['wxr', 'rest'], true)
                || !in_array($runScope, ['all', 'selection'], true) || !is_string($completedAt)) {
                throw new \UnexpectedValueException('Invalid WordPress import run summary.');
            }
            $runs[] = [
                'id' => $id,
                'source' => $runSource,
                'input' => $runInput,
                'scope' => $runScope,
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
