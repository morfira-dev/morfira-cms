<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

final class ContentTypeVersionConflict extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The content type was changed by another editor. Reload it before saving again.');
    }
}
