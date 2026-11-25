<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store editorial page reviews for immutable page revisions.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS content_page_reviews (
    id CHAR(36) NOT NULL,
    page_id CHAR(36) NOT NULL,
    revision_number INT NOT NULL,
    page_title VARCHAR(200) NOT NULL,
    page_slug VARCHAR(191) NOT NULL,
    locale VARCHAR(16) NOT NULL,
    status VARCHAR(24) NOT NULL,
    submitted_by CHAR(36) NOT NULL,
    submitted_at DATETIME(6) NOT NULL,
    decided_by CHAR(36) DEFAULT NULL,
    decided_at DATETIME(6) DEFAULT NULL,
    comment VARCHAR(1000) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE INDEX uniq_content_page_review_revision (page_id, revision_number),
    INDEX idx_content_page_reviews_submitted (submitted_at, id),
    INDEX idx_content_page_reviews_status (status, submitted_at, id),
    CONSTRAINT fk_content_page_review_revision FOREIGN KEY (page_id, revision_number)
        REFERENCES content_page_revisions (page_id, revision_number) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS content_page_reviews');
    }
}
