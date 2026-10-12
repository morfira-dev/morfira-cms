<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013093000 extends AbstractMigration
{
    private const DEFAULT_LAYOUT = '{"theme":"morfira","template":"standard","headerNavigationCode":null,"footerNavigationCode":null}';

    public function getDescription(): string
    {
        return 'Version built-in theme, page template and navigation slots with drafts and published snapshots.';
    }

    public function up(Schema $schema): void
    {
        foreach (['content_pages', 'content_page_revisions', 'published_page_snapshots'] as $table) {
            $this->addSql(sprintf("ALTER TABLE %s ADD layout JSON DEFAULT NULL", $table));
            $this->addSql(sprintf("UPDATE %s SET layout = '%s'", $table, self::DEFAULT_LAYOUT));
            $this->addSql(sprintf('ALTER TABLE %s MODIFY layout JSON NOT NULL', $table));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['content_pages', 'content_page_revisions', 'published_page_snapshots'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s DROP layout', $table));
        }
    }
}
