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
}
