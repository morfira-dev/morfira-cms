<?php

declare(strict_types=1);

namespace Cms\Identity\Application;

interface PasswordHasher
{
    /** @param list<string> $roles */
    public function hash(string $id, string $email, array $roles, string $plainPassword): string;
}
