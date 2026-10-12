<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

interface ContentTypeRepository
{
    public function add(ContentType $type): void;

    public function get(ContentTypeId $id): ?ContentType;

    public function findByKey(ContentTypeKey $key): ?ContentType;

    public function save(ContentType $type, int $expectedVersion): void;

    /** @return list<ContentType> */
    public function all(): array;
}
