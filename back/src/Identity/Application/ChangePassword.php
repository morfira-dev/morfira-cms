<?php

declare(strict_types=1);

namespace Cms\Identity\Application;

use Cms\Identity\Domain\CurrentPasswordInvalid;
use Cms\Identity\Domain\UserNotFound;
use Cms\Identity\Domain\UserRepository;

final readonly class ChangePassword
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwordHasher)
    {
    }

    public function __invoke(string $userId, string $currentPassword, string $newPassword): void
    {
        if (mb_strlen($newPassword) < 12) {
            throw new \InvalidArgumentException('Password must contain at least 12 characters.');
        }
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new UserNotFound();
        }
        if (!$this->passwordHasher->verify($user->id, $user->email, $user->roles, $user->passwordHash, $currentPassword)) {
            throw new CurrentPasswordInvalid();
        }

        $this->users->replacePasswordHash($user->id, $this->passwordHasher->hash($user->id, $user->email, $user->roles, $newPassword));
    }
}
