<?php

declare(strict_types=1);

namespace Cms\Tests\Schema;

use Cms\Schema\Domain\ContentType;
use Cms\Schema\Domain\ContentTypeId;
use Cms\Schema\Domain\ContentTypeKey;
use Cms\Schema\Domain\ContentTypeRepository;
use Cms\Schema\Domain\ContentTypeVersionConflict;

final class InMemoryContentTypeRepository implements ContentTypeRepository
{
    /** @var array<string, ContentType> */
    private array $types = [];

    public function add(ContentType $type): void
    {
        $this->types[$type->id->value] = $type;
    }

    public function get(ContentTypeId $id): ?ContentType
    {
        return $this->types[$id->value] ?? null;
    }

    public function findByKey(ContentTypeKey $key): ?ContentType
    {
        foreach ($this->types as $type) {
            if ($type->key->value === $key->value) {
                return $type;
            }
        }

        return null;
    }

    public function save(ContentType $type, int $expectedVersion): void
    {
        $current = $this->types[$type->id->value] ?? null;
        if (null === $current || $current->version !== $expectedVersion) {
            throw new ContentTypeVersionConflict();
        } $this->types[$type->id->value] = $type;
    }

    public function all(): array
    {
        return array_values($this->types);
    }
}
