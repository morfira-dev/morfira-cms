<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119095000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record source permalinks against stable WordPress draft targets for links in subsequent imports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE content_wordpress_import_items
    ADD draft_source_url VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
    ADD draft_kind VARCHAR(4) DEFAULT NULL,
    ADD draft_target_id CHAR(36) DEFAULT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->fetchOne('SELECT 1 FROM content_wordpress_import_items WHERE draft_source_url IS NOT NULL LIMIT 1') !== false,
            'Removing draft permalink mappings would break link resolution during subsequent imports.',
        );
        $this->addSql(<<<'SQL'
ALTER TABLE content_wordpress_import_items
    DROP draft_source_url,
    DROP draft_kind,
    DROP draft_target_id
SQL);
    }
}
