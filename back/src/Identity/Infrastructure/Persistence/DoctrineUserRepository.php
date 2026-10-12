<?php

declare(strict_types=1);

namespace Cms\Identity\Infrastructure\Persistence;

use Cms\Identity\Domain\User;
use Cms\Identity\Domain\UserRepository;
use Doctrine\DBAL\Connection;

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

        return new User(
            self::stringValue($row, 'id'),
            self::stringValue($row, 'email'),
            self::stringValue($row, 'password_hash'),
            $normalizedRoles,
            new \DateTimeImmutable(self::stringValue($row, 'created_at'), new \DateTimeZone('UTC')),
        );
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
