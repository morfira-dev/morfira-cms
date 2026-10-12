<?php

declare(strict_types=1);

namespace Cms\Navigation\Domain;

final readonly class NavigationCode
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1 || strlen($value) > 120) {
            throw new \InvalidArgumentException('Navigation code must contain lowercase letters, numbers and single hyphens.');
        }
    }
}
