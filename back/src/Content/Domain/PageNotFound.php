<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class PageNotFound extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Page not found.');
    }
}
