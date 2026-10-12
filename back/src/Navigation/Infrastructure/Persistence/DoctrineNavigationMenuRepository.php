<?php

declare(strict_types=1);

namespace Cms\Navigation\Infrastructure\Persistence;

use Cms\Navigation\Domain\NavigationCode;
use Cms\Navigation\Domain\NavigationCodeAlreadyExists;
use Cms\Navigation\Domain\NavigationItem;
use Cms\Navigation\Domain\NavigationMenu;
use Cms\Navigation\Domain\NavigationMenuId;
use Cms\Navigation\Domain\NavigationMenuRepository;
use Cms\Navigation\Domain\NavigationVersionConflict;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineNavigationMenuRepository implements NavigationMenuRepository
{
    public function __construct(private Connection $connection) {}

    public function add(NavigationMenu $menu): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($menu): void {
                $connection->insert('navigation_menus', $this->row($menu));
                $this->insertRevision($connection, $menu);
            });
        } catch (UniqueConstraintViolationException) {
            throw new NavigationCodeAlreadyExists($menu->code->value);
        }
    }

    public function get(NavigationMenuId $id): ?NavigationMenu
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM navigation_menus WHERE id = ?', [$id->value]);
        return $row === false ? null : $this->hydrate($row);
    }

    public function save(NavigationMenu $menu, int $expectedVersion): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($menu, $expectedVersion): void {
                $affected = $connection->update('navigation_menus', $this->row($menu), ['id' => $menu->id->value, 'version' => $expectedVersion]);
                if ($affected !== 1) { throw new NavigationVersionConflict(); }
                $this->insertRevision($connection, $menu);
            });
        } catch (UniqueConstraintViolationException) {
            throw new NavigationCodeAlreadyExists($menu->code->value);
        }
    }

    public function all(): array
    {
        return array_map($this->hydrate(...), $this->connection->fetchAllAssociative('SELECT * FROM navigation_menus ORDER BY created_at DESC'));
    }

    /** @return array<string, int|string> */
    private function row(NavigationMenu $menu): array
    {
        return [
            'id' => $menu->id->value,
            'name' => $menu->name,
            'code' => $menu->code->value,
            'items' => json_encode(array_map(static fn (NavigationItem $item): array => $item->toArray(), $menu->items), JSON_THROW_ON_ERROR),
            'version' => $menu->version,
            'created_at' => $menu->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $menu->updatedAt->format('Y-m-d H:i:s.u'),
        ];
    }

    private function insertRevision(Connection $connection, NavigationMenu $menu): void
    {
        $connection->insert('navigation_menu_revisions', [
            'menu_id' => $menu->id->value,
            'revision_number' => $menu->version,
            'name' => $menu->name,
            'code' => $menu->code->value,
            'items' => json_encode(array_map(static fn (NavigationItem $item): array => $item->toArray(), $menu->items), JSON_THROW_ON_ERROR),
            'created_at' => $menu->updatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): NavigationMenu
    {
        return NavigationMenu::reconstitute(
            NavigationMenuId::fromString(self::stringValue($row, 'id')),
            self::stringValue($row, 'name'),
            new NavigationCode(self::stringValue($row, 'code')),
            NavigationItem::listFromArray(self::itemsValue($row)),
            self::intValue($row, 'version'),
            new \DateTimeImmutable(self::stringValue($row, 'created_at'), new \DateTimeZone('UTC')),
            new \DateTimeImmutable(self::stringValue($row, 'updated_at'), new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row
     *  @return list<array<string, mixed>>
     */
    private static function itemsValue(array $row): array
    {
        $items = json_decode(self::stringValue($row, 'items'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items)) { throw new \UnexpectedValueException('Navigation items must be a JSON list.'); }
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) { throw new \UnexpectedValueException('Every navigation item must be an object.'); }
            /** @var array<string, mixed> $item */
            $result[] = $item;
        }
        return $result;
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) { throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key)); }
        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) { throw new \UnexpectedValueException(sprintf('Column "%s" must be an integer.', $key)); }
        return (int) $value;
    }
}
