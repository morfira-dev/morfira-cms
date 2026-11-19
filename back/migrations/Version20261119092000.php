<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119092000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track local legacy URLs against stable publication targets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE published_import_redirects (
    source_path VARBINARY(512) NOT NULL,
    target_type VARCHAR(5) NOT NULL,
    target_id CHAR(36) NOT NULL,
    PRIMARY KEY (source_path)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing imported redirects would break legacy URLs.');
    }
}
