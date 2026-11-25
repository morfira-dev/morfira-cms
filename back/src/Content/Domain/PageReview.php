<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

/** @phpstan-type ReviewData array{id: string, pageId: string, pageTitle: string, pageSlug: string, locale: string, revision: int, status: 'pending'|'approved'|'changes_requested', submittedBy: string, submittedAt: string, decidedBy: ?string, decidedAt: ?string, comment: ?string} */
final readonly class PageReview
{
    public function __construct(
        public string $id,
        public PageId $pageId,
        public string $pageTitle,
        public string $pageSlug,
        public string $locale,
        public int $revision,
        public PageReviewStatus $status,
        public string $submittedBy,
        public \DateTimeImmutable $submittedAt,
        public ?string $decidedBy = null,
        public ?\DateTimeImmutable $decidedAt = null,
        public ?string $comment = null,
    ) {
    }

    public static function submit(PageId $pageId, PageRevision $revision, string $actor, \DateTimeImmutable $now): self
    {
        return new self(
            PageId::new()->value,
            $pageId,
            $revision->title->value,
            $revision->slug->value,
            $revision->locale,
            $revision->revision,
            PageReviewStatus::Pending,
            $actor,
            $now,
        );
    }

    public function decide(PageReviewStatus $status, ?string $comment, string $actor, \DateTimeImmutable $now): self
    {
        if ($this->status !== PageReviewStatus::Pending) {
            throw new PageReviewConflict();
        }
        if ($status === PageReviewStatus::Pending) {
            throw new \InvalidArgumentException('Select a review decision.');
        }

        $trimmed = $comment === null ? '' : mb_trim($comment);
        if (($comment !== null && mb_strlen($comment) > 1000) || ($status === PageReviewStatus::ChangesRequested && $trimmed === '')) {
            throw new \InvalidArgumentException('Comments may contain up to 1000 characters and are required when requesting changes.');
        }

        return new self(
            $this->id,
            $this->pageId,
            $this->pageTitle,
            $this->pageSlug,
            $this->locale,
            $this->revision,
            $status,
            $this->submittedBy,
            $this->submittedAt,
            $actor,
            $now,
            $trimmed === '' ? null : $trimmed,
        );
    }

    /** @return ReviewData */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pageId' => $this->pageId->value,
            'pageTitle' => $this->pageTitle,
            'pageSlug' => $this->pageSlug,
            'locale' => $this->locale,
            'revision' => $this->revision,
            'status' => $this->status->value,
            'submittedBy' => $this->submittedBy,
            'submittedAt' => $this->submittedAt->format(DATE_ATOM),
            'decidedBy' => $this->decidedBy,
            'decidedAt' => $this->decidedAt?->format(DATE_ATOM),
            'comment' => $this->comment,
        ];
    }
}
