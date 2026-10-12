<?php

declare(strict_types=1);

namespace Cms\Tests\Identity\Application;

use Cms\Identity\Application\ChangePassword;
use Cms\Identity\Application\CreateUser;
use Cms\Identity\Domain\CurrentPasswordInvalid;
use Cms\Tests\Identity\FakePasswordHasher;
use Cms\Tests\Identity\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangePasswordTest extends TestCase
{
    #[Test]
    public function it_verifies_the_current_password_before_replacing_the_hash(): void
    {
        $users = new InMemoryUserRepository();
        $user = (new CreateUser($users, new FakePasswordHasher()))('owner@example.test', 'current-password', 'owner');

        (new ChangePassword($users, new FakePasswordHasher()))($user->id, 'current-password', 'replacement-password');

        self::assertSame('hashed:replacement-password', $users->findById($user->id)?->passwordHash);
    }

    #[Test]
    public function it_preserves_the_hash_when_the_current_password_is_wrong(): void
    {
        $users = new InMemoryUserRepository();
        $user = (new CreateUser($users, new FakePasswordHasher()))('owner@example.test', 'current-password', 'owner');

        try {
            (new ChangePassword($users, new FakePasswordHasher()))($user->id, 'wrong-password', 'replacement-password');
            self::fail('Wrong current password must be rejected.');
        } catch (CurrentPasswordInvalid) {
        }

        self::assertSame('hashed:current-password', $users->findById($user->id)?->passwordHash);
    }
}
