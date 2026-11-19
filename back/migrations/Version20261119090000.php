<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track imported WordPress items by source key and external ID.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE content_wordpress_import_items (
    source_key VARCHAR(80) NOT NULL,
    external_id VARCHAR(20) NOT NULL,
    PRIMARY KEY (source_key, external_id)
) DEFAULT CHARACTER SET ascii COLLATE ascii_bin ENGINE=InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing the import ledger would allow previous imports to be replayed.');
    }
}
