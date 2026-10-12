<?php

declare(strict_types=1);

namespace Cms\Schema\Infrastructure\Persistence;

use Cms\Schema\Domain\ContentType;
use Cms\Schema\Domain\ContentTypeId;
use Cms\Schema\Domain\ContentTypeKey;
use Cms\Schema\Domain\ContentTypeKeyAlreadyExists;
use Cms\Schema\Domain\ContentTypeRepository;
use Cms\Schema\Domain\ContentTypeVersionConflict;
use Cms\Schema\Domain\FieldDefinition;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineContentTypeRepository implements ContentTypeRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(ContentType $type): void
    {
        try {
            $this->connection->transactional(function (Connection $c) use ($type): void {
                $c->insert('schema_content_types', $this->row($type));
                $this->revision($c, $type);
            });
        } catch (UniqueConstraintViolationException) {
            throw new ContentTypeKeyAlreadyExists($type->key->value);
        }
    }

    public function get(ContentTypeId $id): ?ContentType
    {
        $r = $this->connection->fetchAssociative('SELECT * FROM schema_content_types WHERE id = ?', [$id->value]);

        return false === $r ? null : $this->hydrate($r);
    }

    public function findByKey(ContentTypeKey $key): ?ContentType
    {
        $r = $this->connection->fetchAssociative('SELECT * FROM schema_content_types WHERE api_name = ?', [$key->value]);

        return false === $r ? null : $this->hydrate($r);
    }

    public function save(ContentType $type, int $expectedVersion): void
    {
        try {
            $this->connection->transactional(function (Connection $c) use ($type, $expectedVersion): void {
                $affected = $c->update('schema_content_types', $this->row($type), ['id' => $type->id->value, 'version' => $expectedVersion]);
                if (1 !== $affected) {
                    throw new ContentTypeVersionConflict();
                }
                $this->revision($c, $type);
            });
        } catch (UniqueConstraintViolationException) {
            throw new ContentTypeKeyAlreadyExists($type->key->value);
        }
    }

    public function all(): array
    {
        return array_map($this->hydrate(...), $this->connection->fetchAllAssociative('SELECT * FROM schema_content_types ORDER BY created_at DESC'));
    }

    /** @return array<string,int|string> */
    private function row(ContentType $t): array
    {
        return ['id' => $t->id->value, 'name' => $t->name, 'api_name' => $t->key->value, 'fields' => json_encode(array_map(static fn (FieldDefinition $f): array => $f->toArray(), $t->fields), JSON_THROW_ON_ERROR), 'version' => $t->version, 'created_at' => $t->createdAt->format('Y-m-d H:i:s.u'), 'updated_at' => $t->updatedAt->format('Y-m-d H:i:s.u')];
    }

    private function revision(Connection $c, ContentType $t): void
    {
        $c->insert('schema_content_type_revisions', ['content_type_id' => $t->id->value, 'revision_number' => $t->version, 'name' => $t->name, 'api_name' => $t->key->value, 'fields' => json_encode(array_map(static fn (FieldDefinition $f): array => $f->toArray(), $t->fields), JSON_THROW_ON_ERROR), 'created_at' => $t->updatedAt->format('Y-m-d H:i:s.u')]);
    }

    /** @param array<string,mixed> $r */
    private function hydrate(array $r): ContentType
    {
        $fields = [];
        foreach (self::objects($r, 'fields') as $field) {
            $fields[] = FieldDefinition::fromArray($field);
        }

        return ContentType::reconstitute(
            ContentTypeId::fromString(self::s($r, 'id')),
            self::s($r, 'name'),
            new ContentTypeKey(self::s($r, 'api_name')),
            $fields,
            self::i($r, 'version'),
            new \DateTimeImmutable(self::s($r, 'created_at'), new \DateTimeZone('UTC')),
            new \DateTimeImmutable(self::s($r, 'updated_at'), new \DateTimeZone('UTC')),
        );
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return list<array<string, mixed>>
     */
    private static function objects(array $r, string $k): array
    {
        $value = json_decode(self::s($r, $k), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value) || !array_is_list($value)) {
            throw new \UnexpectedValueException('Stored fields must be a JSON list.');
        }
        $objects = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new \UnexpectedValueException('Stored field must be an object.');
            }
            /** @var array<string, mixed> $normalizedItem */
            $normalizedItem = $item;
            $objects[] = $normalizedItem;
        }

        return $objects;
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
