<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store linked reusable section placements with page drafts and revisions.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages ADD COLUMN IF NOT EXISTS section_links JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE content_page_revisions ADD COLUMN IF NOT EXISTS section_links JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages DROP COLUMN IF EXISTS section_links');
        $this->addSql('ALTER TABLE content_page_revisions DROP COLUMN IF EXISTS section_links');
    }
}
