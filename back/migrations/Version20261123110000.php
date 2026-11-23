<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index WordPress import runs by source and completion order.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_wordpress_import_runs_source ON content_wordpress_import_runs (source_key, completed_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_wordpress_import_runs_source ON content_wordpress_import_runs');
    }
}
