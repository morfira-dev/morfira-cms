<?php

declare(strict_types=1);

namespace Cms\Navigation\Infrastructure\Persistence;

use Cms\Navigation\Domain\NavigationCodeAlreadyExists;
use Cms\Navigation\Domain\NavigationItem;
use Cms\Navigation\Domain\PublishedNavigationMenu;
use Cms\Navigation\Domain\PublishedNavigationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrinePublishedNavigationRepository implements PublishedNavigationRepository
{
    public function __construct(private Connection $connection) {}

    public function publish(PublishedNavigationMenu $menu): PublishedNavigationMenu
    {
        $existing = $this->findByRevision($menu->menuId, $menu->sourceVersion);
        if ($existing !== null) {
            $this->activate($existing);
            return $existing;
        }

        try {
            $this->connection->transactional(function (Connection $connection) use ($menu): void {
                $connection->insert('published_navigation_snapshots', [
                    'id' => $menu->snapshotId,
                    'menu_id' => $menu->menuId,
                    'source_version' => $menu->sourceVersion,
                    'name' => $menu->name,
                    'code' => $menu->code,
                    'items' => json_encode($menu->items, JSON_THROW_ON_ERROR),
                    'published_at' => $menu->publishedAt->format('Y-m-d H:i:s.u'),
                ]);
                $this->activateUsing($connection, $menu);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findByRevision($menu->menuId, $menu->sourceVersion);
            if ($existing === null) { throw new NavigationCodeAlreadyExists($menu->code); }
            $this->activate($existing);
            return $existing;
        }

        return $menu;
    }

    public function findByCode(string $code): ?PublishedNavigationMenu
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT snapshot.*
FROM published_navigation_routes route
JOIN published_navigation_snapshots snapshot ON snapshot.id = route.snapshot_id
WHERE route.code = ?
SQL, [$code]);
        return $row === false ? null : $this->hydrate($row);
    }

    private function findByRevision(string $menuId, int $sourceVersion): ?PublishedNavigationMenu
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM published_navigation_snapshots WHERE menu_id = ? AND source_version = ?', [$menuId, $sourceVersion]);
        return $row === false ? null : $this->hydrate($row);
    }

    private function activate(PublishedNavigationMenu $menu): void
    {
        try {
            $this->connection->transactional(fn (Connection $connection) => $this->activateUsing($connection, $menu));
        } catch (UniqueConstraintViolationException) {
            throw new NavigationCodeAlreadyExists($menu->code);
        }
    }

    private function activateUsing(Connection $connection, PublishedNavigationMenu $menu): void
    {
        $connection->executeQuery('SELECT code FROM published_navigation_routes WHERE menu_id = ? FOR UPDATE', [$menu->menuId]);
        $connection->delete('published_navigation_routes', ['menu_id' => $menu->menuId]);
        $connection->insert('published_navigation_routes', ['code' => $menu->code, 'menu_id' => $menu->menuId, 'snapshot_id' => $menu->snapshotId]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PublishedNavigationMenu
    {
        $itemPayloads = self::itemsValue($row);
        $items = array_map(static fn (NavigationItem $item): array => $item->toArray(), NavigationItem::listFromArray($itemPayloads));
        return new PublishedNavigationMenu(
            self::stringValue($row, 'id'),
            self::stringValue($row, 'menu_id'),
            self::intValue($row, 'source_version'),
            self::stringValue($row, 'name'),
            self::stringValue($row, 'code'),
            $items,
            new \DateTimeImmutable(self::stringValue($row, 'published_at'), new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    private static function itemsValue(array $row): array
    {
        $items = json_decode(self::stringValue($row, 'items'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items)) { throw new \UnexpectedValueException('Published navigation items must be a JSON list.'); }
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) { throw new \UnexpectedValueException('Every published navigation item must be an object.'); }
            /** @var array<string, mixed> $item */
            $result[] = $item;
        }
        return $result;
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): string { $value = $row[$key] ?? null; if (!is_string($value)) { throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key)); } return $value; }
    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $key): int { $value = $row[$key] ?? null; if (!is_int($value) && !(is_string($value) && ctype_digit($value))) { throw new \UnexpectedValueException(sprintf('Column "%s" must be an integer.', $key)); } return (int) $value; }
}
