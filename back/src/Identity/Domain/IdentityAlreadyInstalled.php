<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

final class IdentityAlreadyInstalled extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The CMS owner has already been configured.');
    }
}
