<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

interface UserRepository
{
    public function isInstalled(): bool;

    public function addFirstOwner(User $user): bool;

    /** @throws UserEmailAlreadyExists */
    public function add(User $user): void;

    /** @return list<User> */
    public function all(): array;

    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    public function changeRolePreservingOwner(string $id, UserRole $role): UserMutationResult;

    public function removePreservingOwner(string $id): UserMutationResult;
}
