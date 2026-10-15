<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class EntryRevisionNotFound extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Entry revision not found.');
    }
}
