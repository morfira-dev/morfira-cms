<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

final class CurrentPasswordInvalid extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Current password is incorrect.');
    }
}
