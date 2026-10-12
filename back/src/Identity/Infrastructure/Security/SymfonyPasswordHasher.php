<?php

declare(strict_types=1);

namespace Cms\Identity\Infrastructure\Security;

use Cms\Identity\Application\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private UserPasswordHasherInterface $hasher)
    {
    }

    public function hash(string $id, string $email, array $roles, string $plainPassword): string
    {
        return $this->hasher->hashPassword(
            new SecurityUser($id, $email, '', $roles),
            $plainPassword,
        );
    }

    /** @param list<string> $roles */
    public function verify(string $id, string $email, array $roles, string $passwordHash, string $plainPassword): bool
    {
        return $this->hasher->isPasswordValid(
            new SecurityUser($id, $email, $passwordHash, $roles),
            $plainPassword,
        );
    }
}
