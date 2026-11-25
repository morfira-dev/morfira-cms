<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Persistence;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageReview;
use Cms\Content\Domain\PageReviewRepository;
use Cms\Content\Domain\PageReviewStatus;
use Cms\Content\Domain\PageRevisionNotFound;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;

final readonly class DoctrinePageReviewRepository implements PageReviewRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function list(?PageReviewStatus $status, ?PageId $pageId, int $limit): array
    {
        $conditions = [];
        $parameters = [];
        $types = [];
        if ($status !== null) {
            $conditions[] = 'status = ?';
            $parameters[] = $status->value;
            $types[] = ParameterType::STRING;
        }
        if ($pageId !== null) {
            $conditions[] = 'page_id = ?';
            $parameters[] = $pageId->value;
            $types[] = ParameterType::STRING;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $parameters[] = $limit;
        $types[] = ParameterType::INTEGER;

        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM content_page_reviews' . $where . ' ORDER BY submitted_at DESC, id ASC LIMIT ?',
            $parameters,
            $types,
        ));
    }

    public function get(string $id): ?PageReview
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM content_page_reviews WHERE id = ?', [$id]);

        return $row === false ? null : self::hydrate($row);
    }

    public function forRevision(PageId $pageId, int $revision): ?PageReview
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM content_page_reviews WHERE page_id = ? AND revision_number = ?',
            [$pageId->value, $revision],
        );

        return $row === false ? null : self::hydrate($row);
    }

    public function add(PageReview $review): PageReview
    {
        try {
            $this->connection->insert('content_page_reviews', [
                'id' => $review->id,
                'page_id' => $review->pageId->value,
                'revision_number' => $review->revision,
                'page_title' => $review->pageTitle,
                'page_slug' => $review->pageSlug,
                'locale' => $review->locale,
                'status' => $review->status->value,
                'submitted_by' => $review->submittedBy,
                'submitted_at' => $review->submittedAt->format('Y-m-d H:i:s.u'),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->forRevision($review->pageId, $review->revision) ?? throw $exception;
        } catch (ForeignKeyConstraintViolationException) {
            throw new PageRevisionNotFound();
        }

        return $review;
    }

    public function saveDecision(PageReview $review): bool
    {
        return $this->connection->update(
            'content_page_reviews',
            [
                'status' => $review->status->value,
                'decided_by' => $review->decidedBy,
                'decided_at' => $review->decidedAt?->format('Y-m-d H:i:s.u'),
                'comment' => $review->comment,
            ],
            ['id' => $review->id, 'status' => PageReviewStatus::Pending->value],
        ) === 1;
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): PageReview
    {
        $number = $row['revision_number'] ?? null;
        if (!is_int($number) && !(is_string($number) && ctype_digit($number))) {
            throw new \UnexpectedValueException('Invalid stored review revision.');
        }
        $zone = new \DateTimeZone('UTC');
        $decidedAt = self::nullableStringValue($row, 'decided_at');

        return new PageReview(
            self::stringValue($row, 'id'),
            PageId::fromString(self::stringValue($row, 'page_id')),
            self::stringValue($row, 'page_title'),
            self::stringValue($row, 'page_slug'),
            self::stringValue($row, 'locale'),
            (int) $number,
            PageReviewStatus::from(self::stringValue($row, 'status')),
            self::stringValue($row, 'submitted_by'),
            new \DateTimeImmutable(self::stringValue($row, 'submitted_at'), $zone),
            self::nullableStringValue($row, 'decided_by'),
            $decidedAt === null ? null : new \DateTimeImmutable($decidedAt, $zone),
            self::nullableStringValue($row, 'comment'),
        );
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): string
    {
        return self::nullableStringValue($row, $key) ?? throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key));
    }

    /** @param array<string, mixed> $row */
    private static function nullableStringValue(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be a string or null.', $key));
        }

        return $value;
    }
}
