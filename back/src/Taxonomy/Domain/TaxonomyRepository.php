<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

interface TaxonomyRepository
{
    public function addTaxonomy(Taxonomy $taxonomy): void;
    public function getTaxonomy(string $id): ?Taxonomy;
    /** @return list<Taxonomy> */
    public function allTaxonomies(): array;
    public function addTerm(Term $term): void;
    public function getTerm(string $id): ?Term;
    /** @return list<Term> */
    public function terms(string $taxonomyId): array;
    /** @param list<string> $termIds */
    public function replaceEntryTerms(string $entryId, array $termIds): void;
    /** @return list<Term> */
    public function entryTerms(string $entryId): array;
}
