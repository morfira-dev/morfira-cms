<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Persistence;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyConflict;
use Cms\Taxonomy\Domain\TaxonomyRepository;
use Cms\Taxonomy\Domain\Term;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrineTaxonomyRepository implements TaxonomyRepository
{
    public function __construct(private Connection $connection) {}

    public function addTaxonomy(Taxonomy $t): void
    {
        try { $this->connection->insert('taxonomy_vocabularies', ['id'=>$t->id,'name'=>$t->name,'slug'=>$t->slug,'hierarchical'=>$t->hierarchical ? 1 : 0,'created_at'=>$this->date($t->createdAt)]); }
        catch (UniqueConstraintViolationException) { throw new TaxonomyConflict('Taxonomy slug is already in use.'); }
    }
    public function saveTaxonomy(Taxonomy $t): void
    {
        try { $this->connection->update('taxonomy_vocabularies',['name'=>$t->name,'slug'=>$t->slug,'hierarchical'=>$t->hierarchical ? 1 : 0],['id'=>$t->id]); }
        catch (UniqueConstraintViolationException) { throw new TaxonomyConflict('Taxonomy slug is already in use.'); }
    }
    public function deleteTaxonomy(string $id): void { $this->connection->delete('taxonomy_vocabularies',['id'=>$id]); }
    public function getTaxonomy(string $id): ?Taxonomy
    {
        $r=$this->connection->fetchAssociative('SELECT * FROM taxonomy_vocabularies WHERE id=?',[$id]); return $r===false?null:$this->taxonomy($r);
    }
    public function allTaxonomies(): array { return array_map($this->taxonomy(...),$this->connection->fetchAllAssociative('SELECT * FROM taxonomy_vocabularies ORDER BY name')); }
    public function addTerm(Term $t): void
    {
        try { $this->connection->insert('taxonomy_terms',['id'=>$t->id,'taxonomy_id'=>$t->taxonomyId,'name'=>$t->name,'slug'=>$t->slug,'parent_id'=>$t->parentId,'created_at'=>$this->date($t->createdAt)]); }
        catch (UniqueConstraintViolationException) { throw new TaxonomyConflict('Term slug is already in use in this taxonomy.'); }
    }
    public function saveTerm(Term $t): void
    {
        try { $this->connection->update('taxonomy_terms',['name'=>$t->name,'slug'=>$t->slug,'parent_id'=>$t->parentId],['id'=>$t->id]); }
        catch (UniqueConstraintViolationException) { throw new TaxonomyConflict('Term slug is already in use in this taxonomy.'); }
    }
    public function deleteTerm(string $id): void { $this->connection->delete('taxonomy_terms',['id'=>$id]); }
    public function getTerm(string $id): ?Term { $r=$this->connection->fetchAssociative('SELECT * FROM taxonomy_terms WHERE id=?',[$id]); return $r===false?null:$this->term($r); }
    public function terms(string $taxonomyId): array { return array_map($this->term(...),$this->connection->fetchAllAssociative('SELECT * FROM taxonomy_terms WHERE taxonomy_id=? ORDER BY name',[$taxonomyId])); }
    public function replaceEntryTerms(string $entryId,array $termIds): void
    {
        $this->connection->transactional(function(Connection $c) use($entryId,$termIds):void { $c->delete('taxonomy_entry_terms',['entry_id'=>$entryId]); foreach($termIds as $id)$c->insert('taxonomy_entry_terms',['entry_id'=>$entryId,'term_id'=>$id]); });
    }
    public function entryTerms(string $entryId): array { return array_map($this->term(...),$this->connection->fetchAllAssociative('SELECT t.* FROM taxonomy_entry_terms a JOIN taxonomy_terms t ON t.id=a.term_id WHERE a.entry_id=? ORDER BY t.name',[$entryId])); }
    /** @param array<string,mixed> $r */ private function taxonomy(array $r):Taxonomy { return new Taxonomy($this->s($r,'id'),$this->s($r,'name'),$this->s($r,'slug'),(bool)$r['hierarchical'],new \DateTimeImmutable($this->s($r,'created_at'),new \DateTimeZone('UTC'))); }
    /** @param array<string,mixed> $r */ private function term(array $r):Term { $p=$r['parent_id']??null; return new Term($this->s($r,'id'),$this->s($r,'taxonomy_id'),$this->s($r,'name'),$this->s($r,'slug'),is_string($p)?$p:null,new \DateTimeImmutable($this->s($r,'created_at'),new \DateTimeZone('UTC'))); }
    /** @param array<string,mixed> $r */ private function s(array $r,string $k):string { $v=$r[$k]??null;if(!is_string($v))throw new \UnexpectedValueException('Invalid taxonomy column.');return $v; }
    private function date(\DateTimeImmutable $d):string{return $d->format('Y-m-d H:i:s.u');}
}
