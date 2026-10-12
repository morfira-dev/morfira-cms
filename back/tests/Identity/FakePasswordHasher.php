<?php

declare(strict_types=1);

namespace Cms\Tests\Identity;

use Cms\Identity\Application\PasswordHasher;

final readonly class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $id, string $email, array $roles, string $plainPassword): string
    {
        return 'hashed:' . $plainPassword;
    }

    /** @param list<string> $roles */
    public function verify(string $id, string $email, array $roles, string $passwordHash, string $plainPassword): bool
    {
        return hash_equals($passwordHash, 'hashed:' . $plainPassword);
    }
}
