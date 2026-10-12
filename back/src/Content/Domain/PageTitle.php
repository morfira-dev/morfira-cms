<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class PageTitle
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 200) {
            throw new \InvalidArgumentException('Title must contain between 1 and 200 characters.');
        }

        $this->value = $value;
    }
}
