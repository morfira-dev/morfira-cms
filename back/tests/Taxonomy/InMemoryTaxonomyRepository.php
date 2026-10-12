<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyRepository;
use Cms\Taxonomy\Domain\Term;

final class InMemoryTaxonomyRepository implements TaxonomyRepository
{
    /** @var array<string,Taxonomy> */ public array $taxonomies=[];
    /** @var array<string,Term> */ public array $termItems=[];
    /** @var array<string,list<string>> */ private array $assignments=[];
    public function addTaxonomy(Taxonomy $taxonomy):void{$this->taxonomies[$taxonomy->id]=$taxonomy;}
    public function getTaxonomy(string $id):?Taxonomy{return $this->taxonomies[$id]??null;}
    public function allTaxonomies():array{return array_values($this->taxonomies);}
    public function addTerm(Term $term):void{$this->termItems[$term->id]=$term;}
    public function getTerm(string $id):?Term{return $this->termItems[$id]??null;}
    public function terms(string $taxonomyId):array{return array_values(array_filter($this->termItems,fn(Term $t)=>$t->taxonomyId===$taxonomyId));}
    public function replaceEntryTerms(string $entryId,array $termIds):void{$this->assignments[$entryId]=$termIds;}
    public function entryTerms(string $entryId):array{return array_values(array_filter($this->termItems,fn(Term $t)=>in_array($t->id,$this->assignments[$entryId]??[],true)));}
}
