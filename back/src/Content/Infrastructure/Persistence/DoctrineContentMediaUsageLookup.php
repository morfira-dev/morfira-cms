<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Persistence;

use Cms\Content\Application\ContentMediaUsageLookup;
use Doctrine\DBAL\Connection;

final readonly class DoctrineContentMediaUsageLookup implements ContentMediaUsageLookup
{
    public function __construct(private Connection $connection)
    {
    }

    public function references(string $assetId): array
    {
        $usages = [];
        foreach ([
            ['content_pages', 'blocks', 'page draft'],
            ['content_pages', 'seo', 'page SEO'],
            ['content_page_revisions', 'blocks', 'page revision'],
            ['content_page_revisions', 'seo', 'page revision SEO'],
            ['content_entries', 'values', 'entry draft'],
            ['content_entry_revisions', 'values', 'entry revision'],
        ] as [$table, $column, $label]) {
            $rows = $this->connection->fetchFirstColumn(sprintf('SELECT %s FROM %s', $column, $table));
            foreach ($rows as $payload) {
                if (is_string($payload) && self::contains(json_decode($payload, true, flags: JSON_THROW_ON_ERROR), $assetId)) {
                    $usages[] = $label;
                    break;
                }
            }
        }

        return array_values(array_unique($usages));
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
