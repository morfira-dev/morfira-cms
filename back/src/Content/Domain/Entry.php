<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class Entry
{
    /** @param array<string,string|int|float|bool|null> $values */
    private function __construct(public EntryId $id, public string $contentTypeId, public string $title, public EntrySlug $slug, public array $values, public int $version, public \DateTimeImmutable $createdAt, public \DateTimeImmutable $updatedAt)
    {
        if ('' === trim($title) || mb_strlen(trim($title)) > 200) {
            throw new \InvalidArgumentException('Entry title must contain between 1 and 200 characters.');
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('Entry version must be positive.');
        }
    }

    /** @param array<string,string|int|float|bool|null> $values */
    public static function create(EntryId $id, string $contentTypeId, string $title, EntrySlug $slug, array $values, \DateTimeImmutable $now): self
    {
        return new self($id, $contentTypeId, trim($title), $slug, $values, 1, $now, $now);
    }

    /** @param array<string,string|int|float|bool|null> $values */
    public static function reconstitute(EntryId $id, string $contentTypeId, string $title, EntrySlug $slug, array $values, int $version, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): self
    {
        return new self($id, $contentTypeId, trim($title), $slug, $values, $version, $createdAt, $updatedAt);
    }

    /** @param array<string,string|int|float|bool|null> $values */
    public function revise(string $title, EntrySlug $slug, array $values, \DateTimeImmutable $now): self
    {
        return new self($this->id, $this->contentTypeId, trim($title), $slug, $values, $this->version + 1, $this->createdAt, $now);
    }
}
