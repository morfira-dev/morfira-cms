<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add localized page variants and immutable public paths.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages ADD locale VARCHAR(16) DEFAULT NULL, ADD translation_group_id CHAR(36) DEFAULT NULL');
        $this->addSql("UPDATE content_pages SET locale = (SELECT default_locale FROM site_settings WHERE id = 1), translation_group_id = id");
        $this->addSql('DROP INDEX uniq_content_pages_slug ON content_pages');
        $this->addSql('ALTER TABLE content_pages MODIFY locale VARCHAR(16) NOT NULL, MODIFY translation_group_id CHAR(36) NOT NULL, ADD UNIQUE INDEX uniq_content_page_locale_slug (locale, slug), ADD UNIQUE INDEX uniq_content_translation_locale (translation_group_id, locale)');
        $this->addSql('ALTER TABLE content_page_revisions ADD locale VARCHAR(16) DEFAULT NULL, ADD translation_group_id CHAR(36) DEFAULT NULL');
        $this->addSql("UPDATE content_page_revisions revision JOIN content_pages page ON page.id = revision.page_id SET revision.locale = page.locale, revision.translation_group_id = page.translation_group_id");
        $this->addSql('ALTER TABLE content_page_revisions MODIFY locale VARCHAR(16) NOT NULL, MODIFY translation_group_id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE published_page_snapshots ADD locale VARCHAR(16) DEFAULT NULL, ADD translation_group_id CHAR(36) DEFAULT NULL, ADD public_path VARCHAR(208) DEFAULT NULL');
        $this->addSql("UPDATE published_page_snapshots snapshot LEFT JOIN content_pages page ON page.id = snapshot.page_id SET snapshot.locale = COALESCE(page.locale, (SELECT default_locale FROM site_settings WHERE id = 1)), snapshot.translation_group_id = COALESCE(page.translation_group_id, snapshot.page_id), snapshot.public_path = snapshot.slug");
        $this->addSql('ALTER TABLE published_page_snapshots MODIFY locale VARCHAR(16) NOT NULL, MODIFY translation_group_id CHAR(36) NOT NULL, MODIFY public_path VARCHAR(208) NOT NULL, ADD INDEX idx_published_translation_group (translation_group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_published_translation_group ON published_page_snapshots');
        $this->addSql('ALTER TABLE published_page_snapshots DROP locale, DROP translation_group_id, DROP public_path');
        $this->addSql('ALTER TABLE content_page_revisions DROP locale, DROP translation_group_id');
        $this->addSql('DROP INDEX uniq_content_page_locale_slug ON content_pages');
        $this->addSql('DROP INDEX uniq_content_translation_locale ON content_pages');
        $this->addSql('ALTER TABLE content_pages DROP locale, DROP translation_group_id, ADD UNIQUE INDEX uniq_content_pages_slug (slug)');
    }
}
