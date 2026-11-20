<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store completed WordPress imports and their reports for Admin Studio.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE content_wordpress_import_runs (
    id CHAR(36) NOT NULL,
    source_key VARCHAR(80) NOT NULL,
    scope VARCHAR(9) NOT NULL,
    completed_at DATETIME(6) NOT NULL,
    counts_json JSON NOT NULL,
    report_json JSON NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_wordpress_import_runs_completed (completed_at, id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENGINE=InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->fetchOne('SELECT 1 FROM content_wordpress_import_runs LIMIT 1') !== false,
            'Removing history would erase completed WordPress import reports.',
        );
        $this->addSql('DROP TABLE content_wordpress_import_runs');
    }
}
