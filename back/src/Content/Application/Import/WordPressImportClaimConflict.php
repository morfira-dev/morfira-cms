<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

final class WordPressImportClaimConflict extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Another import has already claimed this WordPress item.', 0, $previous);
    }
}
