<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add block content, optimistic versions and immutable page revisions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages ADD blocks JSON DEFAULT NULL, ADD version INT NOT NULL DEFAULT 1, ADD updated_at DATETIME(6) DEFAULT NULL');
        $this->addSql("UPDATE content_pages SET blocks = '[]', updated_at = created_at");
        $this->addSql('ALTER TABLE content_pages MODIFY blocks JSON NOT NULL, MODIFY updated_at DATETIME(6) NOT NULL');
        $this->addSql(<<<'SQL'
CREATE TABLE content_page_revisions (
    id BIGINT AUTO_INCREMENT NOT NULL,
    page_id CHAR(36) NOT NULL,
    revision_number INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    blocks JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    INDEX idx_content_page_revisions_page (page_id),
    UNIQUE INDEX uniq_content_page_revision (page_id, revision_number),
    PRIMARY KEY(id),
    CONSTRAINT fk_content_page_revisions_page FOREIGN KEY (page_id) REFERENCES content_pages (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql('INSERT INTO content_page_revisions (page_id, revision_number, title, slug, blocks, created_at) SELECT id, version, title, slug, blocks, updated_at FROM content_pages');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_page_revisions');
        $this->addSql('ALTER TABLE content_pages DROP blocks, DROP version, DROP updated_at');
    }
}
