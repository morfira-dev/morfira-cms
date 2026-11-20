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

    /** @return list<array{externalId:string,url:string,kind:'page'|'post',targetId:string}> */
    public function draftPermalinks(string $source): array;

    /**
     * Record the permalink against a fresh claim in the draft creation transaction.
     *
     * @param 'page'|'post' $kind
     * @throws WordPressImportClaimConflict
     */
    public function recordDraftPermalink(string $source, string $externalId, string $url, string $kind, string $targetId): void;

    public function mediaAssetId(string $source, string $externalId): ?string;

    public function recordMediaAsset(string $source, string $externalId, string $assetId): void;

    public function authorEntryId(string $source, string $loginHash): ?string;

    public function recordAuthor(string $source, string $loginHash, string $entryId): void;

    /**
     * @template T
     * @param \Closure(): T $operation
     * @return T
     */
    public function transactional(\Closure $operation): mixed;
}
