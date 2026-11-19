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
}
