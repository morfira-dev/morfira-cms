<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

final class LastOwnerRequired extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The final owner cannot be demoted or removed.');
    }
}
