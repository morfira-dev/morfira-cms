<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class PageVersionConflict extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The page was changed by another editor. Reload it before saving again.');
    }
}
