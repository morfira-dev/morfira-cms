<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

final class SelfManagementForbidden extends \DomainException
{
    public function __construct()
    {
        parent::__construct('You cannot change the role or remove your own account.');
    }
}
