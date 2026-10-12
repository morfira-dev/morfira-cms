<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create versioned navigation menus and immutable published navigation snapshots.'; }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE navigation_menus (
    id CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(120) NOT NULL,
    items JSON NOT NULL,
    version INT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE INDEX uniq_navigation_menu_code (code),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE navigation_menu_revisions (
    id BIGINT AUTO_INCREMENT NOT NULL,
    menu_id CHAR(36) NOT NULL,
    revision_number INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(120) NOT NULL,
    items JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    INDEX idx_navigation_revision_menu (menu_id),
    UNIQUE INDEX uniq_navigation_menu_revision (menu_id, revision_number),
    PRIMARY KEY(id),
    CONSTRAINT fk_navigation_revision_menu FOREIGN KEY (menu_id) REFERENCES navigation_menus (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE published_navigation_snapshots (
    id CHAR(36) NOT NULL,
    menu_id CHAR(36) NOT NULL,
    source_version INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(120) NOT NULL,
    items JSON NOT NULL,
    published_at DATETIME(6) NOT NULL,
    INDEX idx_published_navigation_menu (menu_id),
    UNIQUE INDEX uniq_published_navigation_version (menu_id, source_version),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE published_navigation_routes (
    code VARCHAR(120) NOT NULL,
    menu_id CHAR(36) NOT NULL,
    snapshot_id CHAR(36) NOT NULL,
    UNIQUE INDEX uniq_published_navigation_route_menu (menu_id),
    UNIQUE INDEX uniq_published_navigation_route_snapshot (snapshot_id),
    PRIMARY KEY(code),
    CONSTRAINT fk_published_navigation_route_snapshot FOREIGN KEY (snapshot_id) REFERENCES published_navigation_snapshots (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE published_navigation_routes');
        $this->addSql('DROP TABLE published_navigation_snapshots');
        $this->addSql('DROP TABLE navigation_menu_revisions');
        $this->addSql('DROP TABLE navigation_menus');
    }
}
