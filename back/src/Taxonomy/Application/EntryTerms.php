<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryRepository;
use Cms\Taxonomy\Domain\TaxonomyRepository;

final readonly class EntryTerms
{
    public function __construct(private EntryRepository $entries, private TaxonomyRepository $taxonomies)
    {
    }

    /** @param list<string> $termIds
     * @return list<array{id:string,taxonomyId:string,name:string,slug:string,parentId:?string,createdAt:string}>
     */
    public function replace(string $entryId, array $termIds): array
    {
        if ($this->entries->get(EntryId::fromString($entryId)) === null) throw new EntryNotFound();
        $unique = array_values(array_unique($termIds));
        foreach ($unique as $termId) {
            if ($this->taxonomies->getTerm($termId) === null) throw new \OutOfBoundsException('Term not found.');
        }
        $this->taxonomies->replaceEntryTerms($entryId, $unique);
        return $this->get($entryId);
    }

    /** @return list<array{id:string,taxonomyId:string,name:string,slug:string,parentId:?string,createdAt:string}> */
    public function get(string $entryId): array
    {
        if ($this->entries->get(EntryId::fromString($entryId)) === null) {
            throw new EntryNotFound();
        }
        return array_map(TaxonomyView::term(...), $this->taxonomies->entryTerms($entryId));
    }

    /** @return list<array{taxonomyId:string,taxonomySlug:string,termId:string,termSlug:string,name:string}> */
    public function snapshot(string $entryId): array
    {
        $result = [];
        foreach ($this->taxonomies->entryTerms($entryId) as $term) {
            $taxonomy = $this->taxonomies->getTaxonomy($term->taxonomyId);
            if ($taxonomy !== null) $result[] = ['taxonomyId' => $taxonomy->id, 'taxonomySlug' => $taxonomy->slug, 'termId' => $term->id, 'termSlug' => $term->slug, 'name' => $term->name];
        }
        return $result;
    }
}
