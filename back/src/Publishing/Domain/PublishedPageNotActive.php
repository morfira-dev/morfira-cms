<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

final class PublishedPageNotActive extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Publish the page before managing its redirects.');
    }
}
