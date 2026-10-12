<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the preferred Admin Studio locale for each user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE identity_users ADD admin_locale VARCHAR(2) NOT NULL DEFAULT 'en'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_users DROP admin_locale');
    }
}
