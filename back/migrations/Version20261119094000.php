<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track immutable private media visibility.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_assets ADD is_private TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing media visibility could expose private assets.');
    }
}
