<?php

declare(strict_types=1);

namespace Cms\Identity\Infrastructure\Security;

use Cms\Identity\Domain\User;
use Cms\Identity\Domain\AdminLocale;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /** @param list<string> $roles */
    public function __construct(
        public string $id,
        public string $email,
        private string $passwordHash,
        private array $roles,
        public AdminLocale $adminLocale = AdminLocale::English,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self($user->id, $user->email, $user->passwordHash, $user->roles, $user->adminLocale);
    }

    public function getUserIdentifier(): string
    {
        if ($this->email === '') {
            throw new \LogicException('A security user must have a non-empty email.');
        }

        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
