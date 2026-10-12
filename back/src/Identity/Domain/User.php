<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

final readonly class User
{
    /** @param list<string> $roles */
    public function __construct(
        public string $id,
        public string $email,
        public string $passwordHash,
        public array $roles,
        public \DateTimeImmutable $createdAt,
    ) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }
    }
}
