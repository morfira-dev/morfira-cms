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
        if (preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $id) !== 1) {
            throw new \InvalidArgumentException('A valid user identifier is required.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }
        if ($email !== mb_strtolower(trim($email))) {
            throw new \InvalidArgumentException('The email address must be normalized.');
        }

        UserRole::fromRoles($roles);
    }
}
