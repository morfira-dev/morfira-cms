<?php

declare(strict_types=1);

namespace Cms\Identity\Application;

use Cms\Identity\Domain\LastOwnerRequired;
use Cms\Identity\Domain\SelfManagementForbidden;
use Cms\Identity\Domain\UserMutationResult;
use Cms\Identity\Domain\UserNotFound;
use Cms\Identity\Domain\UserRepository;

final readonly class DeleteUser
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(string $actorId, string $userId): void
    {
        if ($actorId === $userId) {
            throw new SelfManagementForbidden();
        }

        $result = $this->users->removePreservingOwner($userId);
        if ($result === UserMutationResult::NotFound) {
            throw new UserNotFound();
        }
        if ($result === UserMutationResult::LastOwner) {
            throw new LastOwnerRequired();
        }
    }
}
