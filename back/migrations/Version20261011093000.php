<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve old published slugs as permanent redirect history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE published_page_redirects (
    source_slug VARCHAR(191) NOT NULL,
    page_id CHAR(36) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    INDEX idx_published_redirect_page (page_id),
    PRIMARY KEY(source_slug)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE published_page_redirects');
    }
}
