<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store and backfill the WordPress import format in run summaries.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE content_wordpress_import_runs ADD COLUMN IF NOT EXISTS input_format VARCHAR(4) NOT NULL DEFAULT 'wxr'");
        $this->addSql(<<<'SQL'
UPDATE content_wordpress_import_runs
SET input_format = 'rest'
WHERE JSON_UNQUOTE(JSON_EXTRACT(report_json, '$.input')) = 'rest'
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_wordpress_import_runs DROP COLUMN input_format');
    }
}
