<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

final class UserEmailAlreadyExists extends \DomainException
{
    public function __construct()
    {
        parent::__construct('A user with this email address already exists.');
    }
}
