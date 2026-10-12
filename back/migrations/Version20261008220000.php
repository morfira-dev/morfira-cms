<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create immutable page delivery snapshots and active public routes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE published_page_snapshots (
    id CHAR(36) NOT NULL,
    page_id CHAR(36) NOT NULL,
    source_revision INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    blocks JSON NOT NULL,
    published_at DATETIME(6) NOT NULL,
    UNIQUE INDEX uniq_published_page_revision (page_id, source_revision),
    INDEX idx_published_page (page_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE published_page_routes (
    slug VARCHAR(191) NOT NULL,
    page_id CHAR(36) NOT NULL,
    snapshot_id CHAR(36) NOT NULL,
    UNIQUE INDEX uniq_published_route_page (page_id),
    UNIQUE INDEX uniq_published_route_snapshot (snapshot_id),
    PRIMARY KEY(slug),
    CONSTRAINT fk_published_route_snapshot FOREIGN KEY (snapshot_id) REFERENCES published_page_snapshots (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE published_page_routes');
        $this->addSql('DROP TABLE published_page_snapshots');
    }
}
