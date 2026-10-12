<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create brokerless publication schedules for page and entry drafts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE publishing_schedules (
    id CHAR(36) NOT NULL,
    target_type VARCHAR(16) NOT NULL,
    target_id CHAR(36) NOT NULL,
    source_version INT NOT NULL,
    scheduled_at DATETIME(6) NOT NULL,
    status VARCHAR(16) NOT NULL,
    failure_reason VARCHAR(500) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE INDEX uniq_publishing_schedule_target (target_type, target_id),
    INDEX idx_publishing_schedule_due (status, scheduled_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE publishing_schedules');
    }
}
