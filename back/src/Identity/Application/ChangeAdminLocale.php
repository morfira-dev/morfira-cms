<?php

declare(strict_types=1);

namespace Cms\Identity\Application;

use Cms\Identity\Domain\AdminLocale;
use Cms\Identity\Domain\UserNotFound;
use Cms\Identity\Domain\UserRepository;

final readonly class ChangeAdminLocale
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(string $userId, string $locale): AdminLocale
    {
        if ($this->users->findById($userId) === null) {
            throw new UserNotFound();
        }

        $adminLocale = AdminLocale::tryFrom($locale)
            ?? throw new \InvalidArgumentException('Admin locale must be en or ru.');
        $this->users->replaceAdminLocale($userId, $adminLocale);

        return $adminLocale;
    }
}
