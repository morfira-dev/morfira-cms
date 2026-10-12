<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class EntryNotFound extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Entry not found.');
    }
}
