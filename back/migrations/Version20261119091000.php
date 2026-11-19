<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record WordPress attachment targets for reuse by later draft imports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_wordpress_import_items ADD media_asset_id CHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing media mappings would break attachment reuse.');
    }
}
