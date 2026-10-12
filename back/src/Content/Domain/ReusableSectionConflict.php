<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class ReusableSectionConflict extends \RuntimeException
{
    public function __construct(string $message = 'The reusable section changed or its name is already in use.', ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
