<?php

declare(strict_types=1);

namespace Cms\Publishing\Infrastructure\Persistence;

use Cms\Publishing\Application\PublishedMediaUsageLookup;
use Doctrine\DBAL\Connection;

final readonly class DoctrinePublishedMediaUsageLookup implements PublishedMediaUsageLookup
{
    public function __construct(private Connection $connection)
    {
    }

    public function references(string $assetId): array
    {
        $usages = [];
        foreach ([
            ['published_page_snapshots', 'blocks', 'published page'],
            ['published_page_snapshots', 'seo', 'published page SEO'],
            ['published_entry_snapshots', 'values', 'published entry'],
        ] as [$table, $column, $label]) {
            foreach ($this->connection->fetchFirstColumn(sprintf('SELECT %s FROM %s', $column, $table)) as $payload) {
                if (is_string($payload) && self::contains(json_decode($payload, true, flags: JSON_THROW_ON_ERROR), $assetId)) {
                    $usages[] = $label;
                    break;
                }
            }
        }

        return $usages;
    }

    private static function contains(mixed $value, string $assetId): bool
    {
        if ($value === $assetId) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $nested) {
            if (self::contains($nested, $assetId)) {
                return true;
            }
        }
        return false;
    }
}
