<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

final class UserNotFound extends \DomainException
{
    public function __construct()
    {
        parent::__construct('User not found.');
    }
}
