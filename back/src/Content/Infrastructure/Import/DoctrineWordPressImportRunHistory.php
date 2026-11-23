<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressImportRunHistory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** @phpstan-import-type RunSummary from WordPressImportRunHistory */
final readonly class DoctrineWordPressImportRunHistory implements WordPressImportRunHistory
{
    public function __construct(private Connection $connection)
    {
    }

    public function record(array $report, string $channel = 'studio'): void
    {
        $utc = new \DateTimeZone('UTC');
        $this->connection->insert('content_wordpress_import_runs', [
            'id' => self::uuid(),
            'source_key' => $report['source'],
            'input_format' => $report['input'],
            'scope' => $report['scope'] ?? 'all',
            'channel' => $channel,
            'completed_at' => new \DateTimeImmutable('now', $utc)->format('Y-m-d H:i:s.u'),
            'counts_json' => json_encode($report['counts'], JSON_THROW_ON_ERROR),
            'report_json' => json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function latest(int $offset, int $limit, ?string $source = null, ?string $input = null, ?string $scope = null, ?string $channel = null): array
    {
        $clauses = [];
        $values = [];
        $types = [];
        foreach (['source_key' => $source, 'input_format' => $input, 'scope' => $scope, 'channel' => $channel] as $column => $value) {
            if ($value !== null) {
                $clauses[] = $column.' = ?';
                $values[] = $value;
                $types[] = ParameterType::STRING;
            }
        }
        $where = $clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, source_key, input_format, scope, channel, completed_at, counts_json FROM content_wordpress_import_runs'.$where.' ORDER BY completed_at DESC, id DESC LIMIT ? OFFSET ?',
            [...$values, $limit, $offset],
            [...$types, ParameterType::INTEGER, ParameterType::INTEGER],
        );

        return array_map(self::runSummary(...), $rows);
    }

    public function sources(int $offset, int $limit, ?string $prefix = null): array
    {
        $where = $prefix === null ? '' : 'WHERE source_key LIKE ?';
        $params = $prefix === null ? [] : [$prefix.'%'];
        $types = $prefix === null ? [] : [ParameterType::STRING];
        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
SELECT latest.id, latest.source_key, latest.input_format, latest.scope, latest.channel, latest.completed_at, latest.counts_json,
       totals.run_count, totals.studio_runs, totals.cli_runs
FROM (
    SELECT source_key, COUNT(*) AS run_count,
           SUM(CASE WHEN channel = 'studio' THEN 1 ELSE 0 END) AS studio_runs,
           SUM(CASE WHEN channel = 'cli' THEN 1 ELSE 0 END) AS cli_runs,
           MAX(completed_at) AS latest_completed_at
    FROM content_wordpress_import_runs
    {$where}
    GROUP BY source_key
    ORDER BY latest_completed_at DESC, source_key ASC
    LIMIT ? OFFSET ?
) totals
JOIN content_wordpress_import_runs latest ON latest.id = (
    SELECT candidate.id
    FROM content_wordpress_import_runs candidate
    WHERE candidate.source_key = totals.source_key AND candidate.completed_at = totals.latest_completed_at
    ORDER BY candidate.id DESC
    LIMIT 1
)
ORDER BY totals.latest_completed_at DESC, totals.source_key ASC
SQL,
            [...$params, $limit, $offset],
            [...$types, ParameterType::INTEGER, ParameterType::INTEGER],
        );

        $sources = [];
        foreach ($rows as $row) {
            $latestRun = self::runSummary($row);
            $runCount = $row['run_count'];
            $studioRuns = $row['studio_runs'];
            $cliRuns = $row['cli_runs'];
            if (!is_numeric($runCount) || !is_numeric($studioRuns) || !is_numeric($cliRuns)) {
                throw new \UnexpectedValueException('Invalid WordPress import source counts.');
            }
            $sources[] = [
                'source' => $latestRun['source'],
                'runCount' => (int) $runCount,
                'studioRuns' => (int) $studioRuns,
                'cliRuns' => (int) $cliRuns,
                'latestRun' => $latestRun,
            ];
        }

        return $sources;
    }

    public function report(string $id): ?\stdClass
    {
        $json = $this->connection->fetchOne('SELECT report_json FROM content_wordpress_import_runs WHERE id = ?', [$id]);

        return $json === false ? null : self::decodeObject($json);
    }

    /**
     * @param array<string,mixed> $row
     * @return RunSummary
     */
    private static function runSummary(array $row): array
    {
        $id = $row['id'];
        $source = $row['source_key'];
        $input = $row['input_format'];
        $scope = $row['scope'];
        $channel = $row['channel'];
        $completedAt = $row['completed_at'];
        if (!is_string($id) || !is_string($source) || !in_array($input, ['wxr', 'rest'], true)
            || !in_array($scope, ['all', 'selection'], true) || !in_array($channel, ['studio', 'cli'], true)
            || !is_string($completedAt)) {
            throw new \UnexpectedValueException('Invalid WordPress import run summary.');
        }

        return [
            'id' => $id,
            'source' => $source,
            'input' => $input,
            'scope' => $scope,
            'channel' => $channel,
            'completedAt' => new \DateTimeImmutable($completedAt, new \DateTimeZone('UTC'))->format(DATE_RFC3339_EXTENDED),
            'counts' => self::decodeObject($row['counts_json']),
        ];
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
