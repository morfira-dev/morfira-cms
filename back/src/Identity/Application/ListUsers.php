<?php

declare(strict_types=1);

namespace Cms\Identity\Application;

use Cms\Identity\Domain\UserRepository;

final readonly class ListUsers
{
    public function __construct(private UserRepository $users)
    {
    }

    /** @return list<UserView> */
    public function __invoke(): array
    {
        return array_map(UserView::fromUser(...), $this->users->all());
    }
}
