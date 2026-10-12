<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class EntryDeletionBlocked extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The entry cannot be deleted while other entries reference it.');
    }
}
