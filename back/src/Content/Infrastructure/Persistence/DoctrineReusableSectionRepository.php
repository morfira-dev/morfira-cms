<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Persistence;

use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\ReusableSection;
use Cms\Content\Domain\ReusableSectionConflict;
use Cms\Content\Domain\ReusableSectionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineReusableSectionRepository implements ReusableSectionRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM content_reusable_sections ORDER BY name ASC');

        return array_map(self::hydrate(...), $rows);
    }

    public function get(string $id): ?ReusableSection
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM content_reusable_sections WHERE id = ?', [$id]);

        return $row === false ? null : self::hydrate($row);
    }

    public function add(ReusableSection $section): void
    {
        try {
            $this->connection->insert('content_reusable_sections', self::row($section));
        } catch (UniqueConstraintViolationException $exception) {
            throw new ReusableSectionConflict(previous: $exception);
        }
    }

    public function save(ReusableSection $section, int $expectedVersion): void
    {
        $row = self::row($section);
        unset($row['id'], $row['created_at']);
        try {
            $affected = $this->connection->update('content_reusable_sections', $row, ['id' => $section->id, 'version' => $expectedVersion]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new ReusableSectionConflict(previous: $exception);
        }
        if ($affected !== 1) {
            throw new ReusableSectionConflict();
        }
    }

    public function remove(string $id): bool
    {
        return $this->connection->delete('content_reusable_sections', ['id' => $id]) === 1;
    }

    /** @return array<string, mixed> */
    private static function row(ReusableSection $section): array
    {
        return [
            'id' => $section->id,
            'name' => $section->name,
            'blocks' => json_encode(array_map(static fn (PageBlock $block): array => $block->toArray(), $section->blocks), JSON_THROW_ON_ERROR),
            'version' => $section->version,
            'created_at' => $section->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $section->updatedAt->format('Y-m-d H:i:s.u'),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): ReusableSection
    {
        $decoded = json_decode(self::stringValue($row, 'blocks'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new \UnexpectedValueException('Reusable section blocks must be a list.');
        }
        $blocks = array_map(static function (mixed $block): PageBlock {
            if (!is_array($block)) {
                throw new \UnexpectedValueException('Reusable section block must be an object.');
            }
            /** @var array<string, mixed> $block */
            return PageBlock::fromArray($block);
        }, $decoded);

        return new ReusableSection(
            self::stringValue($row, 'id'),
            self::stringValue($row, 'name'),
            $blocks,
            self::intValue($row, 'version'),
            new \DateTimeImmutable(self::stringValue($row, 'created_at'), new \DateTimeZone('UTC')),
            new \DateTimeImmutable(self::stringValue($row, 'updated_at'), new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key));
        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) throw new \UnexpectedValueException(sprintf('Column "%s" must be an integer.', $key));
        return (int) $value;
    }
}
