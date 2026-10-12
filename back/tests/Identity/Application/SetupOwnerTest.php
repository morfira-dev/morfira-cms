<?php

declare(strict_types=1);

namespace Cms\Tests\Identity\Application;

use Cms\Identity\Application\PasswordHasher;
use Cms\Identity\Application\SetupOwner\SetupOwner;
use Cms\Identity\Domain\IdentityAlreadyInstalled;
use Cms\Identity\Domain\User;
use Cms\Identity\Domain\UserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SetupOwnerTest extends TestCase
{
    #[Test]
    public function it_creates_exactly_one_owner_with_a_hashed_password(): void
    {
        $users = new InMemoryUserRepository();
        $setup = new SetupOwner($users, new FakePasswordHasher());

        $owner = $setup('Owner@Example.test', 'a-secure-password');

        self::assertSame('owner@example.test', $owner->email);
        self::assertSame(['ROLE_OWNER', 'ROLE_EDITOR'], $owner->roles);
        self::assertSame('hashed:a-secure-password', $owner->passwordHash);
        self::assertTrue($users->isInstalled());
    }

    #[Test]
    public function it_rejects_a_second_owner(): void
    {
        $users = new InMemoryUserRepository();
        $setup = new SetupOwner($users, new FakePasswordHasher());
        $setup('first@example.test', 'a-secure-password');

        $this->expectException(IdentityAlreadyInstalled::class);

        $setup('second@example.test', 'another-password');
    }

    #[Test]
    public function it_rejects_short_passwords(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SetupOwner(new InMemoryUserRepository(), new FakePasswordHasher()))('owner@example.test', 'too-short');
    }
}

final class InMemoryUserRepository implements UserRepository
{
    private ?User $user = null;

    public function isInstalled(): bool
    {
        return $this->user !== null;
    }

    public function addFirstOwner(User $user): bool
    {
        if ($this->user !== null) {
            return false;
        }

        $this->user = $user;
        return true;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->user?->email === $email ? $this->user : null;
    }
}

final readonly class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $id, string $email, array $roles, string $plainPassword): string
    {
        return 'hashed:' . $plainPassword;
    }
}
