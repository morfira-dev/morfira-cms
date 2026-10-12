<?php

declare(strict_types=1);

namespace Cms\Navigation\Domain;

final readonly class NavigationMenu
{
    /** @param list<NavigationItem> $items */
    private function __construct(
        public NavigationMenuId $id,
        public string $name,
        public NavigationCode $code,
        public array $items,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (trim($name) === '' || mb_strlen(trim($name)) > 120) {
            throw new \InvalidArgumentException('Navigation menu name must contain between 1 and 120 characters.');
        }

        if ($version < 1) {
            throw new \InvalidArgumentException('Navigation menu version must be positive.');
        }
    }

    /** @param list<NavigationItem> $items */
    public static function draft(NavigationMenuId $id, string $name, NavigationCode $code, array $items, \DateTimeImmutable $createdAt): self
    {
        return new self($id, trim($name), $code, $items, 1, $createdAt, $createdAt);
    }

    /** @param list<NavigationItem> $items */
    public static function reconstitute(NavigationMenuId $id, string $name, NavigationCode $code, array $items, int $version, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): self
    {
        return new self($id, trim($name), $code, $items, $version, $createdAt, $updatedAt);
    }

    /** @param list<NavigationItem> $items */
    public function revise(string $name, NavigationCode $code, array $items, \DateTimeImmutable $updatedAt): self
    {
        return new self($this->id, trim($name), $code, $items, $this->version + 1, $this->createdAt, $updatedAt);
    }
}
