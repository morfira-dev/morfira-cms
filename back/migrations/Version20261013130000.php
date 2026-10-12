<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create versioned reusable authoring sections.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE content_reusable_sections (id CHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, blocks JSON NOT NULL, version INT NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, UNIQUE INDEX uniq_reusable_section_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_reusable_sections');
    }
}
