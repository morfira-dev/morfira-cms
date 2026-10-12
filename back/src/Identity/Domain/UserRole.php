<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

enum UserRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';

    /** @return list<string> */
    public function securityRoles(): array
    {
        return match ($this) {
            self::Owner => ['ROLE_OWNER', 'ROLE_EDITOR'],
            self::Editor => ['ROLE_EDITOR'],
        };
    }

    /** @param list<string> $roles */
    public static function fromRoles(array $roles): self
    {
        return match ($roles) {
            ['ROLE_OWNER', 'ROLE_EDITOR'] => self::Owner,
            ['ROLE_EDITOR'] => self::Editor,
            default => throw new \InvalidArgumentException('Only Owner and Editor roles are supported.'),
        };
    }
}
