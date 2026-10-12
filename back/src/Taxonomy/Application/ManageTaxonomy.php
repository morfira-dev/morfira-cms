<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyConflict;
use Cms\Taxonomy\Domain\TaxonomyRepository;
use Cms\Taxonomy\Domain\Term;

final readonly class ManageTaxonomy
{
    public function __construct(private TaxonomyRepository $repository)
    {
    }

    public function createTaxonomy(string $name, string $slug, bool $hierarchical): TaxonomyView
    {
        $taxonomy = new Taxonomy(self::uuid(), trim($name), $slug, $hierarchical, self::now());
        $this->repository->addTaxonomy($taxonomy);
        return TaxonomyView::fromTaxonomy($taxonomy, []);
    }

    public function createTerm(string $taxonomyId, string $name, string $slug, ?string $parentId): TaxonomyView
    {
        $taxonomy = $this->repository->getTaxonomy($taxonomyId) ?? throw new \OutOfBoundsException('Taxonomy not found.');
        if (!$taxonomy->hierarchical && $parentId !== null) {
            throw new \InvalidArgumentException('Flat taxonomies cannot contain parent terms.');
        }
        if ($parentId !== null) {
            $parent = $this->repository->getTerm($parentId);
            if ($parent === null || $parent->taxonomyId !== $taxonomyId) {
                throw new \InvalidArgumentException('Parent term must belong to the same taxonomy.');
            }
        }
        $this->repository->addTerm(new Term(self::uuid(), $taxonomyId, trim($name), $slug, $parentId, self::now()));
        return TaxonomyView::fromTaxonomy($taxonomy, $this->repository->terms($taxonomyId));
    }

    public function updateTaxonomy(string $id, string $name, string $slug, bool $hierarchical): TaxonomyView
    {
        $current = $this->repository->getTaxonomy($id) ?? throw new \OutOfBoundsException('Taxonomy not found.');
        $terms = $this->repository->terms($id);
        if (!$hierarchical && array_any($terms, static fn (Term $term): bool => $term->parentId !== null)) {
            throw new TaxonomyConflict('Remove term hierarchy before making this taxonomy flat.');
        }
        $updated = new Taxonomy($current->id, trim($name), $slug, $hierarchical, $current->createdAt);
        $this->repository->saveTaxonomy($updated);

        return TaxonomyView::fromTaxonomy($updated, $terms);
    }

    public function deleteTaxonomy(string $id): void
    {
        if ($this->repository->getTaxonomy($id) === null) throw new \OutOfBoundsException('Taxonomy not found.');
        $this->repository->deleteTaxonomy($id);
    }

    public function updateTerm(string $taxonomyId, string $termId, string $name, string $slug, ?string $parentId): TaxonomyView
    {
        $taxonomy = $this->repository->getTaxonomy($taxonomyId) ?? throw new \OutOfBoundsException('Taxonomy not found.');
        $term = $this->repository->getTerm($termId);
        if ($term === null || $term->taxonomyId !== $taxonomyId) throw new \OutOfBoundsException('Term not found.');
        $this->assertParent($taxonomy, $termId, $parentId);
        $this->repository->saveTerm(new Term($term->id, $term->taxonomyId, trim($name), $slug, $parentId, $term->createdAt));

        return TaxonomyView::fromTaxonomy($taxonomy, $this->repository->terms($taxonomyId));
    }

    public function deleteTerm(string $taxonomyId, string $termId): TaxonomyView
    {
        $taxonomy = $this->repository->getTaxonomy($taxonomyId) ?? throw new \OutOfBoundsException('Taxonomy not found.');
        $term = $this->repository->getTerm($termId);
        if ($term === null || $term->taxonomyId !== $taxonomyId) throw new \OutOfBoundsException('Term not found.');
        if (array_any($this->repository->terms($taxonomyId), static fn (Term $candidate): bool => $candidate->parentId === $termId)) {
            throw new TaxonomyConflict('A term with children cannot be deleted.');
        }
        $this->repository->deleteTerm($termId);

        return TaxonomyView::fromTaxonomy($taxonomy, $this->repository->terms($taxonomyId));
    }

    /** @return list<TaxonomyView> */
    public function list(): array
    {
        return array_map(fn (Taxonomy $taxonomy): TaxonomyView => TaxonomyView::fromTaxonomy($taxonomy, $this->repository->terms($taxonomy->id)), $this->repository->allTaxonomies());
    }

    private function assertParent(Taxonomy $taxonomy, string $termId, ?string $parentId): void
    {
        if ($parentId === null) return;
        if (!$taxonomy->hierarchical) throw new \InvalidArgumentException('Flat taxonomies cannot contain parent terms.');
        if ($parentId === $termId) throw new \InvalidArgumentException('A term cannot be its own parent.');
        $terms = $this->repository->terms($taxonomy->id);
        $byId = [];
        foreach ($terms as $term) $byId[$term->id] = $term;
        $parent = $byId[$parentId] ?? null;
        if ($parent === null) throw new \InvalidArgumentException('Parent term must belong to the same taxonomy.');
        while ($parent !== null) {
            if ($parent->id === $termId) throw new \InvalidArgumentException('Term hierarchy cannot contain a cycle.');
            $parent = $parent->parentId === null ? null : ($byId[$parent->parentId] ?? null);
        }
    }

    private static function now(): \DateTimeImmutable { return new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
    private static function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
