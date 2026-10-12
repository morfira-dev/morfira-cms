<?php

declare(strict_types=1);

namespace Cms\Tests\Identity;

use Cms\Identity\Domain\User;
use Cms\Identity\Domain\UserEmailAlreadyExists;
use Cms\Identity\Domain\UserMutationResult;
use Cms\Identity\Domain\UserRepository;
use Cms\Identity\Domain\UserRole;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function isInstalled(): bool
    {
        return $this->users !== [];
    }

    public function addFirstOwner(User $user): bool
    {
        if ($this->users !== []) {
            return false;
        }

        $this->users[$user->id] = $user;

        return true;
    }

    public function add(User $user): void
    {
        foreach ($this->users as $existing) {
            if ($existing->email === $user->email) {
                throw new UserEmailAlreadyExists();
            }
        }

        $this->users[$user->id] = $user;
    }

    public function all(): array
    {
        return array_values($this->users);
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === mb_strtolower(trim($email))) {
                return $user;
            }
        }

        return null;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function changeRolePreservingOwner(string $id, UserRole $role): UserMutationResult
    {
        $user = $this->users[$id] ?? null;
        if ($user === null) {
            return UserMutationResult::NotFound;
        }
        if (UserRole::fromRoles($user->roles) === UserRole::Owner && $role === UserRole::Editor && $this->ownerCount() <= 1) {
            return UserMutationResult::LastOwner;
        }

        $this->users[$id] = new User($user->id, $user->email, $user->passwordHash, $role->securityRoles(), $user->createdAt);

        return UserMutationResult::Applied;
    }

    public function removePreservingOwner(string $id): UserMutationResult
    {
        $user = $this->users[$id] ?? null;
        if ($user === null) {
            return UserMutationResult::NotFound;
        }
        if (UserRole::fromRoles($user->roles) === UserRole::Owner && $this->ownerCount() <= 1) {
            return UserMutationResult::LastOwner;
        }

        unset($this->users[$id]);

        return UserMutationResult::Applied;
    }

    private function ownerCount(): int
    {
        return count(array_filter(
            $this->users,
            static fn (User $user): bool => UserRole::fromRoles($user->roles) === UserRole::Owner,
        ));
    }
}
