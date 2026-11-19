<?php

declare(strict_types=1);

namespace Cms\Publishing\Infrastructure\Persistence;

use Cms\Publishing\Domain\ImportedRedirectRepository;
use Cms\Publishing\Domain\PublicationTarget;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineImportedRedirectRepository implements ImportedRedirectRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function owner(string $source): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT target_type, target_id FROM published_import_redirects WHERE source_path = ?',
            [$source],
        );
        if ($row === false || !is_string($row['target_type']) || !is_string($row['target_id'])) {
            return null;
        }

        return ['type' => PublicationTarget::from($row['target_type']), 'id' => $row['target_id']];
    }

    public function register(string $source, PublicationTarget $type, string $targetId): bool
    {
        try {
            $this->connection->insert('published_import_redirects', [
                'source_path' => $source,
                'target_type' => $type->value,
                'target_id' => $targetId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function activeTargetPath(string $source): ?string
    {
        $route = $this->connection->fetchAssociative(<<<'SQL'
SELECT page.slug AS page_path, entry.api_name, entry.slug AS entry_slug
FROM published_import_redirects AS alias
LEFT JOIN published_page_routes AS page ON alias.target_type = 'page' AND page.page_id = alias.target_id
LEFT JOIN published_entry_routes AS entry ON alias.target_type = 'entry' AND entry.entry_id = alias.target_id
WHERE alias.source_path = ?
SQL, [$source]);
        if ($route === false) {
            return null;
        }
        $page = $route['page_path'];
        if (is_string($page)) {
            return $page === 'home' ? '/' : '/'.$page;
        }
        if (is_string($route['api_name']) && is_string($route['entry_slug'])) {
            return '/content/'.$route['api_name'].'/'.$route['entry_slug'];
        }

        return null;
    }
}
