<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageReview;
use Cms\Content\Domain\PageReviewConflict;
use Cms\Content\Domain\PageReviewRepository;
use Cms\Content\Domain\PageReviewStatus;
use Cms\Content\Domain\PageRevisionNotFound;

/** @phpstan-import-type ReviewData from PageReview */
final readonly class ManagePageReviews
{
    public function __construct(private PageReviewRepository $reviews, private PageRepository $pages)
    {
    }

    /** @return list<ReviewData> */
    public function list(?PageReviewStatus $status, ?PageId $pageId, int $limit): array
    {
        return array_map(static fn (PageReview $review): array => $review->toArray(), $this->reviews->list($status, $pageId, $limit));
    }

    public function preview(string $id): ?PageRevisionView
    {
        $review = $this->reviews->get($id);
        if ($review === null) {
            return null;
        }

        $revision = $this->pages->revision($review->pageId, $review->revision);

        return $revision === null ? null : PageRevisionView::fromRevision($revision);
    }

    /** @return array{created: bool, review: ReviewData} */
    public function submit(PageId $pageId, int $revision, string $actor): array
    {
        $existing = $this->reviews->forRevision($pageId, $revision);
        if ($existing !== null) {
            return ['created' => false, 'review' => $existing->toArray()];
        }

        $snapshot = $this->pages->revision($pageId, $revision) ?? throw new PageRevisionNotFound();
        $candidate = PageReview::submit($pageId, $snapshot, $actor, self::now());
        $stored = $this->reviews->add($candidate);

        return ['created' => $stored->id === $candidate->id, 'review' => $stored->toArray()];
    }

    /** @return ReviewData */
    public function decide(string $id, PageReviewStatus $status, ?string $comment, string $actor): array
    {
        $review = $this->reviews->get($id) ?? throw new \OutOfBoundsException('Page review not found.');
        $decided = $review->decide($status, $comment, $actor, self::now());
        if (!$this->reviews->saveDecision($decided)) {
            if ($this->reviews->get($id) === null) {
                throw new \OutOfBoundsException('Page review not found.');
            }
            throw new PageReviewConflict();
        }

        return $decided->toArray();
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
