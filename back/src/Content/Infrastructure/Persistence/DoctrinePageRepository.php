<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Persistence;

use Cms\Content\Domain\Page;
use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageLayout;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageRevision;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Domain\PageSlug;
use Cms\Content\Domain\PageSlugAlreadyExists;
use Cms\Content\Domain\PageStatus;
use Cms\Content\Domain\PageTitle;
use Cms\Content\Domain\PageTranslationAlreadyExists;
use Cms\Content\Domain\PageVersionConflict;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrinePageRepository implements PageRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(Page $page): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($page): void {
                $connection->insert('content_pages', $this->row($page));
                $this->insertRevision($connection, $page);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->throwConflict($exception, $page);
        }
    }

    public function get(PageId $id): ?Page
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM content_pages WHERE id = ?',
            [$id->value],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(Page $page, int $expectedVersion): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($page, $expectedVersion): void {
                $affected = $connection->update(
                    'content_pages',
                    $this->row($page),
                    ['id' => $page->id->value, 'version' => $expectedVersion],
                );

                if ($affected !== 1) {
                    throw new PageVersionConflict();
                }

                $this->insertRevision($connection, $page);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->throwConflict($exception, $page);
        }
    }

    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM content_pages ORDER BY created_at DESC',
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function revisions(PageId $id): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM content_page_revisions WHERE page_id = ? ORDER BY revision_number DESC',
            [$id->value],
        );

        return array_map($this->hydrateRevision(...), $rows);
    }

    public function revision(PageId $id, int $revision): ?PageRevision
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM content_page_revisions WHERE page_id = ? AND revision_number = ?',
            [$id->value, $revision],
        );

        return $row === false ? null : $this->hydrateRevision($row);
    }

    /** @return array<string, int|string> */
    private function row(Page $page): array
    {
        return [
            'id' => $page->id->value,
            'title' => $page->title->value,
            'slug' => $page->slug->value,
            'status' => $page->status->value,
            'blocks' => json_encode(array_map(static fn (PageBlock $block): array => $block->toArray(), $page->blocks), JSON_THROW_ON_ERROR),
            'seo' => json_encode($page->seo->toArray(), JSON_THROW_ON_ERROR),
            'layout' => json_encode($page->layout->toArray(), JSON_THROW_ON_ERROR),
            'version' => $page->version,
            'created_at' => $page->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $page->updatedAt->format('Y-m-d H:i:s.u'),
            'locale' => $page->locale,
            'translation_group_id' => $page->translationGroupId,
        ];
    }

    private function insertRevision(Connection $connection, Page $page): void
    {
        $connection->insert('content_page_revisions', [
            'page_id' => $page->id->value,
            'revision_number' => $page->version,
            'title' => $page->title->value,
            'slug' => $page->slug->value,
            'blocks' => json_encode(array_map(static fn (PageBlock $block): array => $block->toArray(), $page->blocks), JSON_THROW_ON_ERROR),
            'seo' => json_encode($page->seo->toArray(), JSON_THROW_ON_ERROR),
            'layout' => json_encode($page->layout->toArray(), JSON_THROW_ON_ERROR),
            'created_at' => $page->updatedAt->format('Y-m-d H:i:s.u'),
            'locale' => $page->locale,
            'translation_group_id' => $page->translationGroupId,
        ]);
    }

    private function throwConflict(UniqueConstraintViolationException $exception, Page $page): never
    {
        if (str_contains($exception->getMessage(), 'uniq_content_translation_locale')) {
            throw new PageTranslationAlreadyExists($page->translationGroupId, $page->locale);
        }

        throw new PageSlugAlreadyExists($page->slug);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Page
    {
        $blocks = json_decode(self::stringValue($row, 'blocks'), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new \UnexpectedValueException('Page blocks must be a JSON list.');
        }

        $seo = json_decode(self::stringValue($row, 'seo'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($seo) || array_is_list($seo)) {
            throw new \UnexpectedValueException('Page SEO settings must be a JSON object.');
        }
        /** @var array<string, mixed> $seo */
        $layout = self::objectValue($row, 'layout', 'Page layout');

        return Page::reconstitute(
            PageId::fromString(self::stringValue($row, 'id')),
            new PageTitle(self::stringValue($row, 'title')),
            new PageSlug(self::stringValue($row, 'slug')),
            PageStatus::from(self::stringValue($row, 'status')),
            array_map(static function (mixed $block): PageBlock {
                if (!is_array($block)) {
                    throw new \UnexpectedValueException('Every stored block must be an object.');
                }

                /** @var array<string, mixed> $block */
                return PageBlock::fromArray($block);
            }, $blocks),
            PageSeo::fromArray($seo),
            self::intValue($row, 'version'),
            new \DateTimeImmutable(self::stringValue($row, 'created_at'), new \DateTimeZone('UTC')),
            new \DateTimeImmutable(self::stringValue($row, 'updated_at'), new \DateTimeZone('UTC')),
            PageLayout::fromArray($layout),
            self::stringValue($row, 'locale'),
            self::stringValue($row, 'translation_group_id'),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateRevision(array $row): PageRevision
    {
        $blocks = json_decode(self::stringValue($row, 'blocks'), true, flags: JSON_THROW_ON_ERROR);
        $seo = json_decode(self::stringValue($row, 'seo'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($blocks) || !array_is_list($blocks) || !is_array($seo) || array_is_list($seo)) {
            throw new \UnexpectedValueException('Stored page revision payload is invalid.');
        }
        /** @var array<string, mixed> $seo */
        $layout = self::objectValue($row, 'layout', 'Page revision layout');

        return new PageRevision(
            self::intValue($row, 'revision_number'),
            new PageTitle(self::stringValue($row, 'title')),
            new PageSlug(self::stringValue($row, 'slug')),
            array_map(static function (mixed $block): PageBlock {
                if (!is_array($block)) {
                    throw new \UnexpectedValueException('Every stored block must be an object.');
                }
                /** @var array<string, mixed> $block */
                return PageBlock::fromArray($block);
            }, $blocks),
            PageSeo::fromArray($seo),
            new \DateTimeImmutable(self::stringValue($row, 'created_at'), new \DateTimeZone('UTC')),
            PageLayout::fromArray($layout),
            self::stringValue($row, 'locale'),
            self::stringValue($row, 'translation_group_id'),
        );
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private static function objectValue(array $row, string $key, string $label): array
    {
        $value = json_decode(self::stringValue($row, $key), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value) || array_is_list($value)) {
            throw new \UnexpectedValueException($label . ' must be a JSON object.');
        }
        /** @var array<string, mixed> $value */

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be an integer.', $key));
        }

        return (int) $value;
    }
}
