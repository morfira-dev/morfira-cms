<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create versioned content schemas, generic entries and immutable published entry snapshots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE schema_content_types (
    id CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    api_name VARCHAR(120) NOT NULL,
    fields JSON NOT NULL,
    version INT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE INDEX uniq_schema_content_type_api_name (api_name),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE schema_content_type_revisions (
    id BIGINT AUTO_INCREMENT NOT NULL,
    content_type_id CHAR(36) NOT NULL,
    revision_number INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    api_name VARCHAR(120) NOT NULL,
    fields JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    INDEX idx_schema_revision_type (content_type_id),
    UNIQUE INDEX uniq_schema_content_type_revision (content_type_id, revision_number),
    PRIMARY KEY(id),
    CONSTRAINT fk_schema_revision_type FOREIGN KEY (content_type_id) REFERENCES schema_content_types (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE content_entries (
    id CHAR(36) NOT NULL,
    content_type_id CHAR(36) NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    values_json JSON NOT NULL,
    version INT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    INDEX idx_content_entry_type (content_type_id),
    UNIQUE INDEX uniq_content_entry_type_slug (content_type_id, slug),
    PRIMARY KEY(id),
    CONSTRAINT fk_content_entry_type FOREIGN KEY (content_type_id) REFERENCES schema_content_types (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE content_entry_revisions (
    id BIGINT AUTO_INCREMENT NOT NULL,
    entry_id CHAR(36) NOT NULL,
    revision_number INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    values_json JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    INDEX idx_content_entry_revision_entry (entry_id),
    UNIQUE INDEX uniq_content_entry_revision (entry_id, revision_number),
    PRIMARY KEY(id),
    CONSTRAINT fk_content_entry_revision_entry FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE published_entry_snapshots (
    id CHAR(36) NOT NULL,
    entry_id CHAR(36) NOT NULL,
    source_version INT NOT NULL,
    content_type JSON NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    values_json JSON NOT NULL,
    published_at DATETIME(6) NOT NULL,
    INDEX idx_published_entry (entry_id),
    UNIQUE INDEX uniq_published_entry_version (entry_id, source_version),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE published_entry_routes (
    api_name VARCHAR(120) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    entry_id CHAR(36) NOT NULL,
    snapshot_id CHAR(36) NOT NULL,
    UNIQUE INDEX uniq_published_entry_route_entry (entry_id),
    UNIQUE INDEX uniq_published_entry_route_snapshot (snapshot_id),
    PRIMARY KEY(api_name, slug),
    CONSTRAINT fk_published_entry_route_snapshot FOREIGN KEY (snapshot_id) REFERENCES published_entry_snapshots (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE published_entry_routes');
        $this->addSql('DROP TABLE published_entry_snapshots');
        $this->addSql('DROP TABLE content_entry_revisions');
        $this->addSql('DROP TABLE content_entries');
        $this->addSql('DROP TABLE schema_content_type_revisions');
        $this->addSql('DROP TABLE schema_content_types');
    }
}
