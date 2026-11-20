<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TaxonomyRepository;
use Cms\Taxonomy\Domain\Term;

/**
 * @phpstan-type SourceTerm array{taxonomy:string,slug:string,name:string,parent:string}
 * @phpstan-type SourceAssignment array{taxonomy:string,slug:string}
 * @phpstan-type TermResult array{taxonomy:string,slug:string,state:string}
 * @phpstan-type AssignmentResult array{entryId:string,taxonomy:string,slug:string,state:string}
 */
final readonly class ImportWordPressTaxonomies
{
    private const VOCABULARIES = [
        'category' => ['slug' => 'wordpress-categories', 'name' => 'WordPress categories', 'hierarchical' => true],
        'post_tag' => ['slug' => 'wordpress-tags', 'name' => 'WordPress tags', 'hierarchical' => false],
    ];

    public function __construct(
        private TaxonomyRepository $repository,
        private EntryTerms $entryTerms,
    ) {
    }

    /**
     * Must run in the caller's import transaction when applying a plan.
     *
     * @param list<SourceTerm> $terms
     * @param array<array-key,list<SourceAssignment>> $assignments
     * @return array{terms:list<TermResult>,assignments:list<AssignmentResult>}
     */
    public function __invoke(array $terms, array $assignments, bool $apply): array
    {
        if ($terms === [] && $assignments === []) {
            return ['terms' => [], 'assignments' => []];
        }

        $vocabularies = [];
        foreach ($this->repository->allTaxonomies() as $vocabulary) {
            $vocabularies[$vocabulary->slug] = $vocabulary;
        }

        $termResults = [];
        $resolved = [];
        foreach (self::VOCABULARIES as $taxonomy => $definition) {
            $source = array_values(array_filter($terms, static fn (array $term): bool => $term['taxonomy'] === $taxonomy));
            $existing = $vocabularies[$definition['slug']] ?? null;
            if ($existing !== null && $existing->hierarchical !== $definition['hierarchical']) {
                foreach ($source as $term) {
                    $termResults[] = ['taxonomy' => $taxonomy, 'slug' => $term['slug'], 'state' => 'conflict'];
                }
                continue;
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $vocabulary = $existing ?? new Taxonomy(self::uuid(), $definition['name'], $definition['slug'], $definition['hierarchical'], $now);
            [$available, $results, $created] = $this->planTerms($vocabulary, $source, $existing !== null, $now, $apply);

            if ($apply && $created !== []) {
                if ($existing === null) {
                    $this->repository->addTaxonomy($vocabulary);
                }
                foreach ($created as $term) {
                    $this->repository->addTerm($term);
                }
            }

            $resolved[$taxonomy] = $available;
            foreach ($source as $term) {
                $slug = $term['slug'];
                $termResults[] = ['taxonomy' => $taxonomy, 'slug' => $slug, 'state' => $results[self::slugKey($slug)]];
            }
        }

        return ['terms' => $termResults, 'assignments' => $this->assign($assignments, $resolved, $apply)];
    }

    /**
     * Keep all declarations for each referenced term and its ancestors.
     *
     * @param list<SourceTerm> $terms
     * @param list<SourceAssignment> $references
     * @return list<SourceTerm>
     */
    public static function referencedDefinitions(array $terms, array $references): array
    {
        $parents = [];
        foreach ($terms as $term) {
            if ($term['parent'] !== '') {
                $parents[self::termKey($term['taxonomy'], $term['slug'])][] = self::termKey($term['taxonomy'], $term['parent']);
            }
        }

        $queue = array_map(static fn (array $term): string => self::termKey($term['taxonomy'], $term['slug']), $references);
        $seen = [];
        for ($index = 0; $index < count($queue); ++$index) {
            $key = $queue[$index];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            foreach ($parents[$key] ?? [] as $parent) {
                $queue[] = $parent;
            }
        }

        return array_values(array_filter($terms, static fn (array $term): bool => isset($seen[self::termKey($term['taxonomy'], $term['slug'])])));
    }

    private static function termKey(string $taxonomy, string $slug): string
    {
        return $taxonomy.':'.self::slugKey($slug);
    }

    /**
     * @param list<SourceTerm> $source
     * @return array{array<array-key,Term>,array<array-key,string>,list<Term>}
     */
    private function planTerms(Taxonomy $vocabulary, array $source, bool $exists, \DateTimeImmutable $now, bool $apply): array
    {
        $available = [];
        if ($exists) {
            foreach ($this->repository->terms($vocabulary->id) as $term) {
                $available[$term->slug] = $term;
            }
        }
        $definitions = $children = $results = $queue = $created = [];
        foreach ($source as $term) {
            $slug = self::slugKey($term['slug']);
            if (isset($definitions[$slug]) && ($definitions[$slug]['name'] !== $term['name']
                || self::slugKey($definitions[$slug]['parent']) !== self::slugKey($term['parent']))) {
                $results[$slug] = 'conflict';
                unset($available[$slug]);
            }
            $definitions[$slug] ??= $term;
        }

        foreach ($definitions as $slug => $term) {
            $parent = self::slugKey($term['parent']);
            if ($parent === '' || !isset($definitions[$parent])) {
                $queue[] = $slug;
            } else {
                $children[$parent][] = $slug;
            }
        }

        for ($index = 0; $index < count($queue); ++$index) {
            $slug = $queue[$index];
            $definition = $definitions[$slug];
            $parent = self::slugKey($definition['parent']);
            if (!isset($results[$slug])) {
                $parentAvailable = $parent === '' || ($vocabulary->hierarchical && isset($available[$parent]));
                $term = $parentAvailable
                    ? $this->makeTerm($vocabulary, $definition, $parent === '' ? null : $available[$parent]->id, $now)
                    : null;
                $existing = $available[$slug] ?? null;
                if ($term === null) {
                    $results[$slug] = 'invalid';
                    unset($available[$slug]);
                } elseif ($existing !== null && $existing->parentId !== $term->parentId) {
                    $results[$slug] = 'conflict';
                    unset($available[$slug]);
                } elseif ($existing !== null) {
                    $results[$slug] = 'existing';
                } else {
                    $available[$slug] = $term;
                    $created[] = $term;
                    $results[$slug] = $apply ? 'imported' : 'planned';
                }
            }
            foreach ($children[$slug] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        // A term without a result depends on a cycle. Preserve the existing catalog.
        foreach (array_keys(array_diff_key($definitions, $results)) as $slug) {
            $results[$slug] = 'invalid';
            unset($available[$slug]);
        }

        return [$available, $results, $created];
    }

    /** @param SourceTerm $source */
    private function makeTerm(Taxonomy $vocabulary, array $source, ?string $parentId, \DateTimeImmutable $now): ?Term
    {
        try {
            return new Term(self::uuid(), $vocabulary->id, trim($source['name']), self::slugKey($source['slug']), $parentId, $now);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param array<array-key,list<SourceAssignment>> $assignments
     * @param array<string,array<array-key,Term>> $resolved
     * @return list<AssignmentResult>
     */
    private function assign(array $assignments, array $resolved, bool $apply): array
    {
        $results = [];
        foreach ($assignments as $entryKey => $memberships) {
            $entryId = (string) $entryKey;
            $termIds = $apply ? array_column($this->entryTerms->get($entryId), 'id') : [];
            $selected = array_fill_keys($termIds, true);
            $seen = [];
            foreach ($memberships as $membership) {
                $taxonomy = $membership['taxonomy'];
                $slug = $membership['slug'];
                $key = self::slugKey($slug);
                if (isset($seen[$taxonomy][$key])) {
                    continue;
                }
                $seen[$taxonomy][$key] = true;
                $term = $resolved[$taxonomy][$key] ?? null;
                if ($term === null) {
                    $state = 'missing_term';
                } elseif (isset($selected[$term->id])) {
                    $state = 'existing';
                } else {
                    $selected[$term->id] = true;
                    $state = $apply ? 'imported' : 'planned';
                }
                $results[] = ['entryId' => $entryId, 'taxonomy' => $taxonomy, 'slug' => $slug, 'state' => $state];
            }
            if ($apply && count($selected) > count($termIds)) {
                $this->entryTerms->replace($entryId, array_keys($selected));
            }
        }

        return $results;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    private static function slugKey(string $slug): string
    {
        $decoded = rawurldecode($slug);
        if (preg_match('/[^\x00-\x7F]/', $decoded) !== 1 || preg_match('/\A[\p{L}\p{M}\p{N}]+(?:-[\p{L}\p{M}\p{N}]+)*\z/u', $decoded) !== 1) {
            return $slug;
        }

        return 'wp-'.substr(hash('sha256', $decoded), 0, 32);
    }
}
