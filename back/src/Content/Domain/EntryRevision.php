<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class EntryRevision
{
    /** @param array<string,string|int|float|bool|list<string>|null> $values */
    public function __construct(public int $revision, public string $title, public EntrySlug $slug, public array $values, public \DateTimeImmutable $createdAt)
    {
        if ($revision < 1) {
            throw new \InvalidArgumentException('Entry revision number must be positive.');
        }
    }
}
