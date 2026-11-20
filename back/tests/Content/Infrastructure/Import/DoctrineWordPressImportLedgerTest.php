<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressImportClaimConflict;
use Cms\Content\Infrastructure\Import\DoctrineWordPressImportLedger;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class DoctrineWordPressImportLedgerTest extends TestCase
{
    public function testReadsHistoricalMappingsForTheRequestedSource(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('WHERE source_key = ? AND draft_source_url IS NOT NULL'), ['legacy-site'])
            ->willReturn([[
                'external_id' => '99999999999999999999',
                'draft_source_url' => 'https://source.example.test/новости/',
                'draft_kind' => 'post',
                'draft_target_id' => '14141414-1414-4414-8414-141414141414',
            ]]);

        self::assertSame([[
            'externalId' => '99999999999999999999',
            'url' => 'https://source.example.test/новости/',
            'kind' => 'post',
            'targetId' => '14141414-1414-4414-8414-141414141414',
        ]], (new DoctrineWordPressImportLedger($connection))->draftPermalinks('legacy-site'));
    }

    public function testRecordsAPermalinkOnlyOnAnUnmappedMaterialClaim(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))
            ->method('executeStatement')
            ->with(
                self::stringContains('WHERE source_key = ? AND external_id = ? AND draft_source_url IS NULL AND media_asset_id IS NULL'),
                ['https://source.example.test/?page_id=42', 'page', '14141414-1414-4414-8414-141414141414', 'legacy-site', '42'],
            )
            ->willReturnOnConsecutiveCalls(1, 0);
        $ledger = new DoctrineWordPressImportLedger($connection);
        $ledger->recordDraftPermalink('legacy-site', '42', 'https://source.example.test/?page_id=42', 'page', '14141414-1414-4414-8414-141414141414');

        $this->expectException(WordPressImportClaimConflict::class);
        $ledger->recordDraftPermalink('legacy-site', '42', 'https://source.example.test/?page_id=42', 'page', '14141414-1414-4414-8414-141414141414');
    }
}
