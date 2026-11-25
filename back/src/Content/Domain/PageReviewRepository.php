<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

interface PageReviewRepository
{
    /** @return list<PageReview> */
    public function list(?PageReviewStatus $status, ?PageId $pageId, int $limit): array;

    /**
     * A concurrent delete or decision can change the stored result.
     *
     * @phpstan-impure
     */
    public function get(string $id): ?PageReview;

    public function forRevision(PageId $pageId, int $revision): ?PageReview;

    /** Returns the review already stored for this revision if a concurrent submission wins. */
    public function add(PageReview $review): PageReview;

    /** Returns false if the review was decided or removed before this write. */
    public function saveDecision(PageReview $review): bool;
}
