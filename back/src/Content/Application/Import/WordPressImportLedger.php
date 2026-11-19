<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

interface WordPressImportLedger
{
    /** A source is a lowercase ASCII key of at most 80 characters. */
    public function contains(string $source, string $externalId): bool;

    /**
     * Claim a canonical positive decimal external ID of at most 20 characters.
     * Call inside the same transaction as the corresponding draft creation.
     *
     * @throws WordPressImportClaimConflict
     */
    public function claim(string $source, string $externalId): void;

    public function mediaAssetId(string $source, string $externalId): ?string;

    public function recordMediaAsset(string $source, string $externalId, string $assetId): void;

    /**
     * @template T
     * @param \Closure(): T $operation
     * @return T
     */
    public function transactional(\Closure $operation): mixed;
}
