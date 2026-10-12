<?php

declare(strict_types=1);

namespace Cms\Identity\Application;

use Cms\Identity\Domain\User;
use Cms\Identity\Domain\UserRepository;
use Cms\Identity\Domain\UserRole;

final readonly class CreateUser
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwordHasher)
    {
    }

    public function __invoke(string $email, string $password, string $role): UserView
    {
        $email = mb_strtolower(trim($email));
        if (mb_strlen($password) < 12) {
            throw new \InvalidArgumentException('Password must contain at least 12 characters.');
        }

        $userRole = UserRole::tryFrom($role)
            ?? throw new \InvalidArgumentException('Role must be either owner or editor.');
        $id = self::newUuid();
        $securityRoles = $userRole->securityRoles();
        $user = new User(
            $id,
            $email,
            $this->passwordHasher->hash($id, $email, $securityRoles, $password),
            $securityRoles,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        $this->users->add($user);

        return UserView::fromUser($user);
    }

    private static function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
