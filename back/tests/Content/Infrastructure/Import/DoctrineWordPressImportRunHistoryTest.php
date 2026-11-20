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
                self::stringContains('WHERE source_key = ? AND input_format = ? AND scope = ? ORDER BY completed_at DESC, id DESC LIMIT ? OFFSET ?'),
                ['legacy-site', 'rest', 'selection', 21, 20],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER],
            )
            ->willReturn([[
                'id' => '14141414-1414-4414-8414-141414141414',
                'source_key' => 'legacy-site',
                'input_format' => 'rest',
                'scope' => 'selection',
                'completed_at' => '2026-11-20 10:00:00.000000',
                'counts_json' => '{"imported":2}',
            ]]);

        self::assertEquals([[
            'id' => '14141414-1414-4414-8414-141414141414',
            'source' => 'legacy-site',
            'input' => 'rest',
            'scope' => 'selection',
            'completedAt' => '2026-11-20T10:00:00.000+00:00',
            'counts' => (object) ['imported' => 2],
        ]], (new DoctrineWordPressImportRunHistory($connection))->latest(20, 21, 'legacy-site', 'rest', 'selection'));
    }
}
