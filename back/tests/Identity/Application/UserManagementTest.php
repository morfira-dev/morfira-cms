<?php

declare(strict_types=1);

namespace Cms\Tests\Identity\Application;

use Cms\Identity\Application\ChangeUserRole;
use Cms\Identity\Application\CreateUser;
use Cms\Identity\Application\DeleteUser;
use Cms\Identity\Application\ListUsers;
use Cms\Identity\Domain\LastOwnerRequired;
use Cms\Identity\Domain\SelfManagementForbidden;
use Cms\Identity\Domain\UserEmailAlreadyExists;
use Cms\Identity\Domain\UserRole;
use Cms\Tests\Identity\FakePasswordHasher;
use Cms\Tests\Identity\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserManagementTest extends TestCase
{
    #[Test]
    public function it_creates_and_lists_safe_normalized_user_views(): void
    {
        $users = new InMemoryUserRepository();
        $create = new CreateUser($users, new FakePasswordHasher());

        $created = $create(' Editor@Example.test ', 'a-secure-password', 'editor');
        $listed = (new ListUsers($users))();

        self::assertSame('editor@example.test', $created->email);
        self::assertSame('editor', $created->role);
        self::assertEquals([$created], $listed);
        self::assertArrayNotHasKey('passwordHash', $created->toArray());
        self::assertSame('hashed:a-secure-password', $users->findById($created->id)?->passwordHash);
    }

    #[Test]
    public function it_rejects_duplicate_email_after_normalization(): void
    {
        $users = new InMemoryUserRepository();
        $create = new CreateUser($users, new FakePasswordHasher());
        $create('person@example.test', 'a-secure-password', 'editor');

        $this->expectException(UserEmailAlreadyExists::class);

        $create(' PERSON@example.test ', 'another-password', 'owner');
    }

    #[Test]
    public function it_changes_another_users_role_and_removes_the_account(): void
    {
        $users = new InMemoryUserRepository();
        $create = new CreateUser($users, new FakePasswordHasher());
        $actor = $create('owner@example.test', 'a-secure-password', 'owner');
        $target = $create('editor@example.test', 'a-secure-password', 'editor');

        $updated = (new ChangeUserRole($users))($actor->id, $target->id, 'owner');
        self::assertSame('owner', $updated->role);
        $stored = $users->findById($target->id);
        self::assertNotNull($stored);
        self::assertSame(UserRole::Owner, UserRole::fromRoles($stored->roles));

        (new DeleteUser($users))($actor->id, $target->id);
        self::assertNull($users->findById($target->id));
    }

    #[Test]
    public function it_rejects_self_demoting_and_self_deletion(): void
    {
        $users = new InMemoryUserRepository();
        $actor = (new CreateUser($users, new FakePasswordHasher()))('owner@example.test', 'a-secure-password', 'owner');

        try {
            (new ChangeUserRole($users))($actor->id, $actor->id, 'editor');
            self::fail('Self-demotion must be rejected.');
        } catch (SelfManagementForbidden) {
        }

        $this->expectException(SelfManagementForbidden::class);
        (new DeleteUser($users))($actor->id, $actor->id);
    }

    #[Test]
    public function it_preserves_the_final_owner_for_role_changes_and_deletion(): void
    {
        $users = new InMemoryUserRepository();
        $create = new CreateUser($users, new FakePasswordHasher());
        $lastOwner = $create('last-owner@example.test', 'a-secure-password', 'owner');
        $actor = $create('other-owner@example.test', 'a-secure-password', 'owner');
        (new ChangeUserRole($users))($lastOwner->id, $actor->id, 'editor');

        try {
            (new ChangeUserRole($users))($actor->id, $lastOwner->id, 'editor');
            self::fail('The final owner must not be demoted.');
        } catch (LastOwnerRequired) {
        }

        $this->expectException(LastOwnerRequired::class);
        (new DeleteUser($users))($actor->id, $lastOwner->id);
    }

    #[Test]
    public function it_rejects_invalid_roles_and_short_passwords(): void
    {
        $create = new CreateUser(new InMemoryUserRepository(), new FakePasswordHasher());

        try {
            $create('person@example.test', 'short', 'editor');
            self::fail('Short passwords must be rejected.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $create('person@example.test', 'a-secure-password', 'administrator');
    }
}
