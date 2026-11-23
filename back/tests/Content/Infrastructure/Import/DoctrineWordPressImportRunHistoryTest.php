<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Infrastructure\Import\DoctrineWordPressImportRunHistory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

final class DoctrineWordPressImportRunHistoryTest extends TestCase
{
    public function testReadsAFilteredPageInStableOrder(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::stringContains('WHERE source_key = ? AND input_format = ? AND scope = ? AND channel = ? ORDER BY completed_at DESC, id DESC LIMIT ? OFFSET ?'),
                ['legacy-site', 'rest', 'all', 'cli', 21, 20],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER],
            )
            ->willReturn([[
                'id' => '14141414-1414-4414-8414-141414141414',
                'source_key' => 'legacy-site',
                'input_format' => 'rest',
                'scope' => 'all',
                'channel' => 'cli',
                'completed_at' => '2026-11-20 10:00:00.000000',
                'counts_json' => '{"imported":2}',
            ]]);

        self::assertEquals([[
            'id' => '14141414-1414-4414-8414-141414141414',
            'source' => 'legacy-site',
            'input' => 'rest',
            'scope' => 'all',
            'channel' => 'cli',
            'completedAt' => '2026-11-20T10:00:00.000+00:00',
            'counts' => (object) ['imported' => 2],
        ]], (new DoctrineWordPressImportRunHistory($connection))->latest(20, 21, 'legacy-site', 'rest', 'all', 'cli'));
    }

    public function testPersistsTheExecutionChannelWithoutChangingReport(): void
    {
        $report = [
            'mode' => 'apply',
            'input' => 'wxr',
            'source' => 'legacy-site',
            'counts' => (object) ['imported' => 1],
            'skippedTypes' => (object) [],
            'items' => [],
            'media' => [],
            'taxonomy' => ['terms' => [], 'assignments' => []],
            'authors' => [],
        ];
        $storedChannels = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('insert')->willReturnCallback(
            static function (string $table, array $data) use ($report, &$storedChannels): int {
                self::assertSame('content_wordpress_import_runs', $table);
                self::assertIsString($data['report_json']);
                self::assertJsonStringEqualsJsonString(json_encode($report, JSON_THROW_ON_ERROR), $data['report_json']);
                $storedChannels[] = $data['channel'];

                return 1;
            },
        );
        $history = new DoctrineWordPressImportRunHistory($connection);

        $history->record($report);
        $history->record($report, 'cli');

        self::assertSame(['studio', 'cli'], $storedChannels);
    }

    public function testReadsAFilteredSourcePageWithoutFetchingReportBodies(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::callback(static function (string $sql): bool {
                    self::assertStringNotContainsString('report_json', $sql);
                    self::assertMatchesRegularExpression('/WHERE source_key LIKE \?\s+GROUP BY source_key\s+ORDER BY latest_completed_at DESC, source_key ASC\s+LIMIT \? OFFSET \?/', $sql);
                    self::assertStringContainsString('candidate.completed_at = totals.latest_completed_at', $sql);
                    self::assertStringContainsString('ORDER BY candidate.id DESC', $sql);

                    return true;
                }),
                ['legacy-%', 21, 20],
                [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER],
            )
            ->willReturn([[
                'id' => '14141414-1414-4414-8414-141414141414',
                'source_key' => 'legacy-site',
                'input_format' => 'rest',
                'scope' => 'all',
                'channel' => 'cli',
                'completed_at' => '2026-11-20 10:00:00.000000',
                'counts_json' => '{"imported":2}',
                'run_count' => 3,
                'studio_runs' => '1',
                'cli_runs' => '2',
            ]]);

        self::assertEquals([[
            'source' => 'legacy-site',
            'runCount' => 3,
            'studioRuns' => 1,
            'cliRuns' => 2,
            'latestRun' => [
                'id' => '14141414-1414-4414-8414-141414141414',
                'source' => 'legacy-site',
                'input' => 'rest',
                'scope' => 'all',
                'channel' => 'cli',
                'completedAt' => '2026-11-20T10:00:00.000+00:00',
                'counts' => (object) ['imported' => 2],
            ],
        ]], (new DoctrineWordPressImportRunHistory($connection))->sources(20, 21, 'legacy-'));
    }
}
