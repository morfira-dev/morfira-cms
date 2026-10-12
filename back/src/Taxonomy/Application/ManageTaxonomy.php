<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
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

    /** @return list<TaxonomyView> */
    public function list(): array
    {
        return array_map(fn (Taxonomy $taxonomy): TaxonomyView => TaxonomyView::fromTaxonomy($taxonomy, $this->repository->terms($taxonomy->id)), $this->repository->allTaxonomies());
    }

    private static function now(): \DateTimeImmutable { return new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
    private static function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
