<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressImportClaimConflict;
use Cms\Content\Application\Import\WordPressImportLedger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineWordPressImportLedger implements WordPressImportLedger
{
    public function __construct(private Connection $connection)
    {
    }

    public function contains(string $source, string $externalId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM content_wordpress_import_items WHERE source_key = ? AND external_id = ?',
            [$source, $externalId],
        ) !== false;
    }

    public function claim(string $source, string $externalId): void
    {
        try {
            $this->connection->insert('content_wordpress_import_items', [
                'source_key' => $source,
                'external_id' => $externalId,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new WordPressImportClaimConflict($exception);
        }
    }

    public function transactional(\Closure $operation): mixed
    {
        return $this->connection->transactional($operation);
    }

    public function draftPermalinks(string $source): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT external_id, draft_source_url, draft_kind, draft_target_id
FROM content_wordpress_import_items
WHERE source_key = ? AND draft_source_url IS NOT NULL
ORDER BY external_id
SQL, [$source]);
        $permalinks = [];
        foreach ($rows as $row) {
            $id = $row['external_id'];
            $url = $row['draft_source_url'];
            $kind = $row['draft_kind'];
            $targetId = $row['draft_target_id'];
            if (!is_string($id) || !is_string($url) || !in_array($kind, ['page', 'post'], true) || !is_string($targetId)) {
                throw new \UnexpectedValueException('Invalid WordPress draft permalink in the import ledger.');
            }
            $permalinks[] = ['externalId' => $id, 'url' => $url, 'kind' => $kind, 'targetId' => $targetId];
        }

        return $permalinks;
    }

    public function recordDraftPermalink(string $source, string $externalId, string $url, string $kind, string $targetId): void
    {
        $affected = $this->connection->executeStatement(<<<'SQL'
UPDATE content_wordpress_import_items
SET draft_source_url = ?, draft_kind = ?, draft_target_id = ?
WHERE source_key = ? AND external_id = ? AND draft_source_url IS NULL AND media_asset_id IS NULL
SQL, [$url, $kind, $targetId, $source, $externalId]);
        if ((int) $affected !== 1) {
            throw new WordPressImportClaimConflict();
        }
    }

    public function mediaAssetId(string $source, string $externalId): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT media_asset_id FROM content_wordpress_import_items WHERE source_key = ? AND external_id = ?',
            [$source, $externalId],
        );

        return is_string($id) ? $id : null;
    }

    public function recordMediaAsset(string $source, string $externalId, string $assetId): void
    {
        $this->connection->update('content_wordpress_import_items', ['media_asset_id' => $assetId], [
            'source_key' => $source,
            'external_id' => $externalId,
        ]);
    }

    public function authorEntryId(string $source, string $loginHash): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT entry_id FROM content_wordpress_import_authors WHERE source_key = ? AND login_hash = ?',
            [$source, $loginHash],
        );

        return is_string($id) ? $id : null;
    }

    public function recordAuthor(string $source, string $loginHash, string $entryId): void
    {
        try {
            $this->connection->insert('content_wordpress_import_authors', [
                'source_key' => $source,
                'login_hash' => $loginHash,
                'entry_id' => $entryId,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new WordPressImportClaimConflict($exception);
        }
    }
}
