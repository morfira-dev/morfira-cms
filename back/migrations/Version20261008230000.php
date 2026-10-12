<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the identity installation guard and CMS users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE identity_installation (
    id TINYINT NOT NULL,
    configured TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql('INSERT INTO identity_installation (id, configured) VALUES (1, 0)');
        $this->addSql(<<<'SQL'
CREATE TABLE identity_users (
    id CHAR(36) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    roles JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    UNIQUE INDEX uniq_identity_user_email (email),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_users');
        $this->addSql('DROP TABLE identity_installation');
    }
}
