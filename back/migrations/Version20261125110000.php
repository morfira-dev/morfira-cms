<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add locale and translation groups to reusable sections.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_reusable_sections ADD COLUMN IF NOT EXISTS locale VARCHAR(6) DEFAULT NULL, ADD COLUMN IF NOT EXISTS translation_group_id CHAR(36) DEFAULT NULL');
        $this->addSql('UPDATE content_reusable_sections SET translation_group_id = id WHERE translation_group_id IS NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_reusable_section_group_locale ON content_reusable_sections (translation_group_id, locale)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_reusable_section_group_locale ON content_reusable_sections');
        $this->addSql('ALTER TABLE content_reusable_sections DROP COLUMN IF EXISTS locale, DROP COLUMN IF EXISTS translation_group_id');
    }
}
