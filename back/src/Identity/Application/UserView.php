<?php

declare(strict_types=1);

namespace Cms\Identity\Application;

use Cms\Identity\Domain\User;
use Cms\Identity\Domain\UserRole;

final readonly class UserView
{
    public function __construct(
        public string $id,
        public string $email,
        public string $role,
        public string $createdAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            $user->id,
            $user->email,
            UserRole::fromRoles($user->roles)->value,
            $user->createdAt->format(DATE_RFC3339_EXTENDED),
        );
    }

    /** @return array{id: string, email: string, role: string, createdAt: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'createdAt' => $this->createdAt,
        ];
    }
}
