<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the execution channel for WordPress import runs.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE content_wordpress_import_runs ADD COLUMN IF NOT EXISTS channel VARCHAR(6) NOT NULL DEFAULT 'studio'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_wordpress_import_runs DROP COLUMN IF EXISTS channel');
    }
}
