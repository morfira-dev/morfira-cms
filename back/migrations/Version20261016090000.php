<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve ordering for multiple entry relations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_entry_relations DROP PRIMARY KEY, ADD position INT UNSIGNED NOT NULL DEFAULT 0 AFTER field_key, ADD PRIMARY KEY (source_entry_id, field_key, position), ADD UNIQUE INDEX uniq_entry_relation_target (source_entry_id, field_key, target_entry_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_entry_relations DROP INDEX uniq_entry_relation_target, DROP PRIMARY KEY, DROP COLUMN position, ADD PRIMARY KEY (source_entry_id, field_key)');
    }
}
