<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class PageReviewConflict extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This page review has already been decided.');
    }
}
