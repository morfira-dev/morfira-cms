<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

final class ContentTypeFieldsConflict extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Field identifiers and keys must be unique.');
    }
}
