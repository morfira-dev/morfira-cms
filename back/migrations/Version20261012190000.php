<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index mutable entry relations and protect referenced targets from deletion.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE content_entry_relations (
    source_entry_id CHAR(36) NOT NULL,
    field_key VARCHAR(120) NOT NULL,
    target_entry_id CHAR(36) NOT NULL,
    INDEX idx_entry_relation_target (target_entry_id),
    PRIMARY KEY(source_entry_id, field_key),
    CONSTRAINT fk_entry_relation_source FOREIGN KEY (source_entry_id) REFERENCES content_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_entry_relation_target FOREIGN KEY (target_entry_id) REFERENCES content_entries(id) ON DELETE RESTRICT
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB
SQL);
        $this->addSql(<<<'SQL'
INSERT INTO content_entry_relations (source_entry_id, field_key, target_entry_id)
SELECT entry.id, field_definition.field_key, JSON_UNQUOTE(JSON_EXTRACT(entry.values_json, CONCAT('$.', field_definition.field_key)))
FROM content_entries entry
JOIN schema_content_types content_type ON content_type.id = entry.content_type_id
JOIN JSON_TABLE(
    content_type.fields,
    '$[*]' COLUMNS(
        field_key VARCHAR(120) PATH '$.key',
        field_type VARCHAR(20) PATH '$.type'
    )
) AS field_definition ON TRUE
JOIN content_entries target ON target.id = JSON_UNQUOTE(JSON_EXTRACT(entry.values_json, CONCAT('$.', field_definition.field_key)))
WHERE field_definition.field_type = 'relation'
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_entry_relations');
    }
}
