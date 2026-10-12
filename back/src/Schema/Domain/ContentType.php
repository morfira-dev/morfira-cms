<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

final readonly class ContentType
{
    /** @param list<FieldDefinition> $fields */
    private function __construct(public ContentTypeId $id, public string $name, public ContentTypeKey $key, public array $fields, public int $version, public \DateTimeImmutable $createdAt, public \DateTimeImmutable $updatedAt)
    {
        if ('' === trim($name) || mb_strlen(trim($name)) > 120) {
            throw new \InvalidArgumentException('Content type name must contain between 1 and 120 characters.');
        }
        if (count($fields) > 50) {
            throw new \InvalidArgumentException('A content type must not contain more than 50 fields.');
        }
        $ids = [];
        $keys = [];
        foreach ($fields as $field) {
            if (isset($ids[$field->id]) || isset($keys[$field->key])) {
                throw new ContentTypeFieldsConflict();
            } $ids[$field->id] = true;
            $keys[$field->key] = true;
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('Content type version must be positive.');
        }
    }

    /** @param list<FieldDefinition> $fields */
    public static function create(ContentTypeId $id, string $name, ContentTypeKey $key, array $fields, \DateTimeImmutable $now): self
    {
        return new self($id, trim($name), $key, $fields, 1, $now, $now);
    }

    /** @param list<FieldDefinition> $fields */
    public static function reconstitute(ContentTypeId $id, string $name, ContentTypeKey $key, array $fields, int $version, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): self
    {
        return new self($id, trim($name), $key, $fields, $version, $createdAt, $updatedAt);
    }

    /** @param list<FieldDefinition> $fields */
    public function revise(string $name, ContentTypeKey $key, array $fields, \DateTimeImmutable $now): self
    {
        return new self($this->id, trim($name), $key, $fields, $this->version + 1, $this->createdAt, $now);
    }
}
