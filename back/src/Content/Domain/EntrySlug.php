<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class EntrySlug
{
    public function __construct(public string $value)
    {
        if (1 !== preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) || strlen($value) > 191) {
            throw new \InvalidArgumentException('Entry slug must contain lowercase letters, numbers and single hyphens.');
        }
    }
}
