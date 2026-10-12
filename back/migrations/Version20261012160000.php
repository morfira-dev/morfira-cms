<?php

declare(strict_types=1);

namespace Cms\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012160000 extends AbstractMigration
{
    public function getDescription():string{return 'Create entry taxonomies, assignments and immutable snapshot term indexes.';}
    public function up(Schema $schema):void
    {
        $this->addSql("CREATE TABLE taxonomy_vocabularies (id CHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, hierarchical TINYINT(1) NOT NULL, created_at DATETIME(6) NOT NULL, UNIQUE INDEX uniq_taxonomy_slug (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
        $this->addSql("CREATE TABLE taxonomy_terms (id CHAR(36) NOT NULL, taxonomy_id CHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, parent_id CHAR(36) DEFAULT NULL, created_at DATETIME(6) NOT NULL, INDEX idx_term_taxonomy (taxonomy_id), INDEX idx_term_parent (parent_id), UNIQUE INDEX uniq_taxonomy_term_slug (taxonomy_id,slug), PRIMARY KEY(id), CONSTRAINT fk_term_taxonomy FOREIGN KEY (taxonomy_id) REFERENCES taxonomy_vocabularies(id) ON DELETE CASCADE, CONSTRAINT fk_term_parent FOREIGN KEY (parent_id) REFERENCES taxonomy_terms(id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
        $this->addSql("CREATE TABLE taxonomy_entry_terms (entry_id CHAR(36) NOT NULL, term_id CHAR(36) NOT NULL, INDEX idx_assignment_entry (entry_id), INDEX idx_assignment_term (term_id), PRIMARY KEY(entry_id,term_id), CONSTRAINT fk_assignment_term FOREIGN KEY (term_id) REFERENCES taxonomy_terms(id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
        $this->addSql("ALTER TABLE published_entry_snapshots ADD taxonomies JSON NOT NULL DEFAULT ('[]')");
        $this->addSql("CREATE TABLE published_entry_terms (snapshot_id CHAR(36) NOT NULL, taxonomy_slug VARCHAR(120) NOT NULL, term_slug VARCHAR(120) NOT NULL, INDEX idx_published_term_filter (taxonomy_slug,term_slug), PRIMARY KEY(snapshot_id,taxonomy_slug,term_slug), CONSTRAINT fk_published_term_snapshot FOREIGN KEY (snapshot_id) REFERENCES published_entry_snapshots(id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB");
    }
    public function down(Schema $schema):void{$this->addSql('DROP TABLE published_entry_terms');$this->addSql('ALTER TABLE published_entry_snapshots DROP taxonomies');$this->addSql('DROP TABLE taxonomy_entry_terms');$this->addSql('DROP TABLE taxonomy_terms');$this->addSql('DROP TABLE taxonomy_vocabularies');}
}
