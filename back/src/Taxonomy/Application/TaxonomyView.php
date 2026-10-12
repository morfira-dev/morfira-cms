<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;

final readonly class TaxonomyView
{
    /** @param list<array{id:string,taxonomyId:string,name:string,slug:string,parentId:?string,createdAt:string}> $terms */
    public function __construct(public string $id, public string $name, public string $slug, public bool $hierarchical, public string $createdAt, public array $terms)
    {
    }

    /** @param list<Term> $terms */
    public static function fromTaxonomy(Taxonomy $taxonomy, array $terms): self
    {
        return new self($taxonomy->id, $taxonomy->name, $taxonomy->slug, $taxonomy->hierarchical, $taxonomy->createdAt->format(DATE_ATOM), array_map(self::term(...), $terms));
    }

    /** @return array{id:string,name:string,slug:string,hierarchical:bool,createdAt:string,terms:list<array{id:string,taxonomyId:string,name:string,slug:string,parentId:?string,createdAt:string}>} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'hierarchical' => $this->hierarchical, 'createdAt' => $this->createdAt, 'terms' => $this->terms];
    }

    /** @return array{id:string,taxonomyId:string,name:string,slug:string,parentId:?string,createdAt:string} */
    public static function term(Term $term): array
    {
        return ['id' => $term->id, 'taxonomyId' => $term->taxonomyId, 'name' => $term->name, 'slug' => $term->slug, 'parentId' => $term->parentId, 'createdAt' => $term->createdAt->format(DATE_ATOM)];
    }
}
