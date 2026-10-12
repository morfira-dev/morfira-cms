<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class PageRevisionNotFound extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Page revision not found.');
    }
}
