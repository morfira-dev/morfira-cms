<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

final readonly class ContentTypeKey
{
    public function __construct(public string $value)
    {
        if (1 !== preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $value) || strlen($value) > 120) {
            throw new \InvalidArgumentException('Content type API name must contain lowercase letters, numbers and single hyphens.');
        }
    }
}
