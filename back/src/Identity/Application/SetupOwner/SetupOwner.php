<?php

declare(strict_types=1);

namespace Cms\Identity\Application\SetupOwner;

use Cms\Identity\Application\PasswordHasher;
use Cms\Identity\Domain\IdentityAlreadyInstalled;
use Cms\Identity\Domain\User;
use Cms\Identity\Domain\UserRepository;
use Cms\Identity\Domain\UserRole;

final readonly class SetupOwner
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function __invoke(string $email, string $password): User
    {
        if ($this->users->isInstalled()) {
            throw new IdentityAlreadyInstalled();
        }

        $email = mb_strtolower(trim($email));
        if (mb_strlen($password) < 12) {
            throw new \InvalidArgumentException('Password must contain at least 12 characters.');
        }

        $id = self::newUuid();
        $roles = UserRole::Owner->securityRoles();
        $user = new User(
            $id,
            $email,
            $this->passwordHasher->hash($id, $email, $roles, $password),
            $roles,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if (!$this->users->addFirstOwner($user)) {
            throw new IdentityAlreadyInstalled();
        }

        return $user;
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
