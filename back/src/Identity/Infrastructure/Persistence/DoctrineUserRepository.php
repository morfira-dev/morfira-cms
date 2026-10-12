<?php

declare(strict_types=1);

namespace Cms\Identity\Infrastructure\Persistence;

use Cms\Identity\Domain\User;
use Cms\Identity\Domain\UserEmailAlreadyExists;
use Cms\Identity\Domain\UserMutationResult;
use Cms\Identity\Domain\UserRepository;
use Cms\Identity\Domain\UserRole;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function isInstalled(): bool
    {
        $configured = $this->connection->fetchOne('SELECT configured FROM identity_installation WHERE id = 1');

        return $configured === 1 || $configured === '1';
    }

    public function addFirstOwner(User $user): bool
    {
        return $this->connection->transactional(function (Connection $connection) use ($user): bool {
            $claimed = $connection->executeStatement(
                'UPDATE identity_installation SET configured = 1 WHERE id = 1 AND configured = 0',
            );

            if ($claimed !== 1) {
                return false;
            }

            $connection->insert('identity_users', [
                'id' => $user->id,
                'email' => $user->email,
                'password_hash' => $user->passwordHash,
                'roles' => json_encode($user->roles, JSON_THROW_ON_ERROR),
                'created_at' => $user->createdAt->format('Y-m-d H:i:s.u'),
            ]);

            return true;
        });
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM identity_users WHERE email = ?',
            [mb_strtolower(trim($email))],
        );

        if ($row === false) {
            return null;
        }

        return self::hydrate($row);
    }

    public function add(User $user): void
    {
        try {
            $this->connection->insert('identity_users', self::row($user));
        } catch (UniqueConstraintViolationException) {
            throw new UserEmailAlreadyExists();
        }
    }

    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM identity_users ORDER BY created_at ASC, id ASC');

        return array_map(self::hydrate(...), $rows);
    }

    public function findById(string $id): ?User
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM identity_users WHERE id = ?', [$id]);

        return $row === false ? null : self::hydrate($row);
    }

    public function changeRolePreservingOwner(string $id, UserRole $role): UserMutationResult
    {
        return $this->connection->transactional(function (Connection $connection) use ($id, $role): UserMutationResult {
            $lockedUsers = self::lockedUsers($connection);
            $currentRole = $lockedUsers[$id] ?? null;
            if ($currentRole === null) {
                return UserMutationResult::NotFound;
            }

            if ($currentRole === UserRole::Owner && $role === UserRole::Editor && self::ownerCount($lockedUsers) <= 1) {
                return UserMutationResult::LastOwner;
            }

            $connection->update('identity_users', [
                'roles' => json_encode($role->securityRoles(), JSON_THROW_ON_ERROR),
            ], ['id' => $id]);

            return UserMutationResult::Applied;
        });
    }

    public function removePreservingOwner(string $id): UserMutationResult
    {
        return $this->connection->transactional(function (Connection $connection) use ($id): UserMutationResult {
            $lockedUsers = self::lockedUsers($connection);
            $currentRole = $lockedUsers[$id] ?? null;
            if ($currentRole === null) {
                return UserMutationResult::NotFound;
            }

            if ($currentRole === UserRole::Owner && self::ownerCount($lockedUsers) <= 1) {
                return UserMutationResult::LastOwner;
            }

            $connection->delete('identity_users', ['id' => $id]);

            return UserMutationResult::Applied;
        });
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): User
    {
        return new User(
            self::stringValue($row, 'id'),
            self::stringValue($row, 'email'),
            self::stringValue($row, 'password_hash'),
            self::roles($row),
            new \DateTimeImmutable(self::stringValue($row, 'created_at'), new \DateTimeZone('UTC')),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private static function roles(array $row): array
    {
        $roles = json_decode(self::stringValue($row, 'roles'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($roles) || !array_is_list($roles)) {
            throw new \UnexpectedValueException('User roles must be a JSON list.');
        }

        $normalizedRoles = [];
        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new \UnexpectedValueException('Every user role must be a string.');
            }
            $normalizedRoles[] = $role;
        }

        return $normalizedRoles;
    }

    /** @return array<string, string> */
    private static function row(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'password_hash' => $user->passwordHash,
            'roles' => json_encode($user->roles, JSON_THROW_ON_ERROR),
            'created_at' => $user->createdAt->format('Y-m-d H:i:s.u'),
        ];
    }

    /** @return array<string, UserRole> */
    private static function lockedUsers(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative('SELECT id, roles FROM identity_users ORDER BY id FOR UPDATE');
        $users = [];
        foreach ($rows as $row) {
            $users[self::stringValue($row, 'id')] = UserRole::fromRoles(self::roles($row));
        }

        return $users;
    }

    /** @param array<string, UserRole> $users */
    private static function ownerCount(array $users): int
    {
        return count(array_filter($users, static fn (UserRole $role): bool => $role === UserRole::Owner));
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key));
        }
        return $value;
    }
}
