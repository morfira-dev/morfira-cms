<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

final class WordPressStudioRestReadFailed extends \RuntimeException
{
    public function __construct(\Throwable $previous)
    {
        parent::__construct('Could not read the public WordPress REST API.', previous: $previous);
    }
}
