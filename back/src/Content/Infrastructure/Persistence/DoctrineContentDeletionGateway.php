<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Persistence;

use Cms\Content\Application\ContentDeletionGateway;
use Cms\Content\Domain\EntryDeletionBlocked;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;

final readonly class DoctrineContentDeletionGateway implements ContentDeletionGateway
{
    public function __construct(private Connection $connection)
    {
    }

    public function deletePage(string $pageId): void
    {
        $this->connection->transactional(function (Connection $connection) use ($pageId): void {
            $connection->delete('published_page_routes', ['page_id' => $pageId]);
            $connection->delete('published_page_redirects', ['page_id' => $pageId]);
            $connection->delete('publishing_schedules', ['target_type' => 'page', 'target_id' => $pageId]);
            $connection->delete('content_pages', ['id' => $pageId]);
        });
    }

    public function deleteEntry(string $entryId): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($entryId): void {
                $connection->delete('published_entry_routes', ['entry_id' => $entryId]);
                $connection->delete('publishing_schedules', ['target_type' => 'entry', 'target_id' => $entryId]);
                $connection->delete('taxonomy_entry_terms', ['entry_id' => $entryId]);
                $connection->delete('content_entry_relations', ['source_entry_id' => $entryId]);
                $connection->delete('content_entries', ['id' => $entryId]);
            });
        } catch (ForeignKeyConstraintViolationException) {
            throw new EntryDeletionBlocked();
        }
    }

    public function referencesToEntry(string $entryId): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT source.id, source.content_type_id, source.title, relation.field_key
FROM content_entry_relations relation
JOIN content_entries source ON source.id = relation.source_entry_id
WHERE relation.target_entry_id = ? AND relation.source_entry_id <> ?
ORDER BY source.title, relation.field_key, relation.position
SQL, [$entryId, $entryId]);

        return array_map(static function (array $row): array {
            $id = $row['id'] ?? null;
            $title = $row['title'] ?? null;
            $contentTypeId = $row['content_type_id'] ?? null;
            $fieldKey = $row['field_key'] ?? null;
            if (!is_string($id) || !is_string($contentTypeId) || !is_string($title) || !is_string($fieldKey)) {
                throw new \UnexpectedValueException('Invalid entry relation index row.');
            }

            return ['id' => $id, 'contentTypeId' => $contentTypeId, 'title' => $title, 'fieldKey' => $fieldKey];
        }, $rows);
    }
}
