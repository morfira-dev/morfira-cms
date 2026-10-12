<?php

declare(strict_types=1);

namespace Cms\Identity\Application;

use Cms\Identity\Domain\LastOwnerRequired;
use Cms\Identity\Domain\SelfManagementForbidden;
use Cms\Identity\Domain\UserMutationResult;
use Cms\Identity\Domain\UserNotFound;
use Cms\Identity\Domain\UserRepository;
use Cms\Identity\Domain\UserRole;

final readonly class ChangeUserRole
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(string $actorId, string $userId, string $role): UserView
    {
        if ($actorId === $userId) {
            throw new SelfManagementForbidden();
        }

        $userRole = UserRole::tryFrom($role)
            ?? throw new \InvalidArgumentException('Role must be either owner or editor.');
        $result = $this->users->changeRolePreservingOwner($userId, $userRole);
        if ($result === UserMutationResult::NotFound) {
            throw new UserNotFound();
        }
        if ($result === UserMutationResult::LastOwner) {
            throw new LastOwnerRequired();
        }

        $user = $this->users->findById($userId) ?? throw new UserNotFound();

        return UserView::fromUser($user);
    }
}
