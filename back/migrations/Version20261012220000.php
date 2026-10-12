<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create versioned singleton site settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE site_settings (id TINYINT NOT NULL, site_name VARCHAR(120) NOT NULL, default_locale VARCHAR(16) NOT NULL, timezone VARCHAR(64) NOT NULL, version INT NOT NULL, updated_at DATETIME(6) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
        $this->addSql("INSERT INTO site_settings (id, site_name, default_locale, timezone, version, updated_at) VALUES (1, 'Morfira CMS', 'en', 'UTC', 1, UTC_TIMESTAMP(6))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_settings');
    }
}
