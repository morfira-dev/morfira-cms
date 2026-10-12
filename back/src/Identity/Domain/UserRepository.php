<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

interface UserRepository
{
    public function isInstalled(): bool;

    public function addFirstOwner(User $user): bool;

    public function findByEmail(string $email): ?User;
}
