<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010203000 extends AbstractMigration
{
    private const DEFAULT_SEO = '{"title":"","description":"","noIndex":false,"openGraphImageAssetId":null}';

    public function getDescription(): string
    {
        return 'Add revisioned SEO settings to drafts and immutable publication snapshots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages ADD seo JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE content_page_revisions ADD seo JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE published_page_snapshots ADD seo JSON DEFAULT NULL');
        $this->addSql("UPDATE content_pages SET seo = '" . self::DEFAULT_SEO . "'");
        $this->addSql("UPDATE content_page_revisions SET seo = '" . self::DEFAULT_SEO . "'");
        $this->addSql("UPDATE published_page_snapshots SET seo = '" . self::DEFAULT_SEO . "'");
        $this->addSql('ALTER TABLE content_pages MODIFY seo JSON NOT NULL');
        $this->addSql('ALTER TABLE content_page_revisions MODIFY seo JSON NOT NULL');
        $this->addSql('ALTER TABLE published_page_snapshots MODIFY seo JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages DROP seo');
        $this->addSql('ALTER TABLE content_page_revisions DROP seo');
        $this->addSql('ALTER TABLE published_page_snapshots DROP seo');
    }
}
