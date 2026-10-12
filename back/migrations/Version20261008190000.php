<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial content pages table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE content_pages (
    id CHAR(36) NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY(id),
    UNIQUE INDEX uniq_content_pages_slug (slug)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_pages');
    }
}
