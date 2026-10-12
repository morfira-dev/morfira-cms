<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add searchable collection and tag metadata to media assets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE media_assets ADD collection VARCHAR(120) DEFAULT NULL AFTER default_alt, ADD tags JSON DEFAULT NULL AFTER collection");
        $this->addSql("UPDATE media_assets SET tags = JSON_ARRAY()");
        $this->addSql('ALTER TABLE media_assets MODIFY tags JSON NOT NULL');
        $this->addSql('CREATE INDEX idx_media_assets_collection ON media_assets (collection)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_media_assets_collection ON media_assets');
        $this->addSql('ALTER TABLE media_assets DROP collection, DROP tags');
    }
}
