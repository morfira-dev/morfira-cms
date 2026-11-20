<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class PageTitle
{
    public const int MAX_LENGTH = 200;

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Title must contain between 1 and 200 characters.');
        }

        $this->value = $value;
    }
}
