<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Persistence;

use Cms\Content\Domain\Entry;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\EntrySlug;
use Cms\Content\Domain\EntrySlugAlreadyExists;
use Cms\Content\Domain\EntryVersionConflict;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineEntryRepository implements EntryRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(Entry $e): void
    {
        try {
            $this->connection->transactional(function (Connection $c) use ($e): void {
                $c->insert('content_entries', $this->row($e));
                $this->revision($c, $e);
                $this->replaceRelationIndex($c, $e);
            });
        } catch (UniqueConstraintViolationException) {
            throw new EntrySlugAlreadyExists($e->slug->value);
        }
    }

    public function get(EntryId $id): ?Entry
    {
        $r = $this->connection->fetchAssociative('SELECT * FROM content_entries WHERE id = ?', [$id->value]);

        return false === $r ? null : $this->hydrate($r);
    }

    public function save(Entry $e, int $expectedVersion): void
    {
        try {
            $this->connection->transactional(function (Connection $c) use ($e, $expectedVersion): void {
                $n = $c->update('content_entries', $this->row($e), ['id' => $e->id->value, 'version' => $expectedVersion]);
                if (1 !== $n) {
                    throw new EntryVersionConflict();
                }
                $this->revision($c, $e);
                $this->replaceRelationIndex($c, $e);
            });
        } catch (UniqueConstraintViolationException) {
            throw new EntrySlugAlreadyExists($e->slug->value);
        }
    }

    public function all(?string $contentTypeId = null): array
    {
        $rows = null === $contentTypeId ? $this->connection->fetchAllAssociative('SELECT * FROM content_entries ORDER BY created_at DESC') : $this->connection->fetchAllAssociative('SELECT * FROM content_entries WHERE content_type_id = ? ORDER BY created_at DESC', [$contentTypeId]);

        return array_map($this->hydrate(...), $rows);
    }

    /** @return array<string,int|string> */
    private function row(Entry $e): array
    {
        return ['id' => $e->id->value, 'content_type_id' => $e->contentTypeId, 'title' => $e->title, 'slug' => $e->slug->value, 'values_json' => json_encode($e->values, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT), 'version' => $e->version, 'created_at' => $e->createdAt->format('Y-m-d H:i:s.u'), 'updated_at' => $e->updatedAt->format('Y-m-d H:i:s.u')];
    }

    private function revision(Connection $c, Entry $e): void
    {
        $c->insert('content_entry_revisions', ['entry_id' => $e->id->value, 'revision_number' => $e->version, 'title' => $e->title, 'slug' => $e->slug->value, 'values_json' => json_encode($e->values, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT), 'created_at' => $e->updatedAt->format('Y-m-d H:i:s.u')]);
    }

    private function replaceRelationIndex(Connection $connection, Entry $entry): void
    {
        $connection->delete('content_entry_relations', ['source_entry_id' => $entry->id->value]);
        $encodedFields = $connection->fetchOne('SELECT fields FROM schema_content_types WHERE id = ?', [$entry->contentTypeId]);
        if (!is_string($encodedFields)) {
            throw new \UnexpectedValueException('Content type fields must be stored as JSON.');
        }
        $fields = json_decode($encodedFields, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($fields) || !array_is_list($fields)) {
            throw new \UnexpectedValueException('Content type fields must be a JSON list.');
        }
        foreach ($fields as $field) {
            if (!is_array($field) || ($field['type'] ?? null) !== 'relation') {
                continue;
            }
            $key = $field['key'] ?? null;
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Relation field key must be a string.');
            }
            $targetId = $entry->values[$key] ?? null;
            if (!is_string($targetId)) {
                continue;
            }
            $connection->insert('content_entry_relations', [
                'source_entry_id' => $entry->id->value,
                'field_key' => $key,
                'target_entry_id' => $targetId,
            ]);
        }
    }

    /** @param array<string,mixed> $r */
    private function hydrate(array $r): Entry
    {
        return Entry::reconstitute(EntryId::fromString(self::s($r, 'id')), self::s($r, 'content_type_id'), self::s($r, 'title'), new EntrySlug(self::s($r, 'slug')), self::values($r), self::i($r, 'version'), new \DateTimeImmutable(self::s($r, 'created_at'), new \DateTimeZone('UTC')), new \DateTimeImmutable(self::s($r, 'updated_at'), new \DateTimeZone('UTC')));
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return array<string, string|int|float|bool|null>
     */
    private static function values(array $r): array
    {
        $value = json_decode(self::s($r, 'values_json'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new \UnexpectedValueException('Entry values must be a JSON object.');
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || (!is_string($item) && !is_int($item) && !is_float($item) && !is_bool($item) && null !== $item)) {
                throw new \UnexpectedValueException('Invalid stored entry value.');
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /** @param array<string,mixed> $r */
    private static function s(array $r, string $k): string
    {
        $v = $r[$k] ?? null;
        if (!is_string($v)) {
            throw new \UnexpectedValueException('Invalid string column.');
        }

        return $v;
    }

    /** @param array<string,mixed> $r */
    private static function i(array $r, string $k): int
    {
        $v = $r[$k] ?? null;
        if (!is_int($v) && !(is_string($v) && ctype_digit($v))) {
            throw new \UnexpectedValueException('Invalid integer column.');
        }

        return (int) $v;
    }
}
