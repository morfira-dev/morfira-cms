<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

final class ContentTypeNotFound extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Content type not found.');
    }
}
