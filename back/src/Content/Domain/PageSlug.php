<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class PageSlug
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1 || strlen($value) > 191) {
            throw new \InvalidArgumentException('Slug must contain lowercase letters, numbers and single hyphens.');
        }
    }
}
