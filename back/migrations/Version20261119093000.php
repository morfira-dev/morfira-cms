<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record WordPress author profile identities without creating login accounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE content_wordpress_import_authors (
    source_key VARCHAR(80) NOT NULL,
    login_hash CHAR(64) NOT NULL,
    entry_id CHAR(36) NOT NULL,
    PRIMARY KEY (source_key, login_hash)
) DEFAULT CHARACTER SET ascii COLLATE ascii_bin ENGINE=InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing author mappings would break identity reuse.');
    }
}
