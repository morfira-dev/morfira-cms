<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Content\Domain\Entry;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntrySlug;
use Cms\Taxonomy\Application\EntryTerms;
use Cms\Taxonomy\Application\ImportWordPressTaxonomies;
use Cms\Taxonomy\Application\ManageTaxonomy;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Taxonomy\InMemoryTaxonomyRepository;
use PHPUnit\Framework\TestCase;

final class ImportWordPressTaxonomiesTest extends TestCase
{
    public function testSelectionPreservesAllDeclarationsAndFollowsNormalizedAncestors(): void
    {
        $terms = [
            ['taxonomy' => 'category', 'slug' => 'child', 'name' => 'First child declaration', 'parent' => 'новости'],
            ['taxonomy' => 'category', 'slug' => 'child', 'name' => 'Conflicting declaration', 'parent' => 'alternative'],
            ['taxonomy' => 'category', 'slug' => rawurlencode('новости'), 'name' => 'Parent', 'parent' => 'child'],
            ['taxonomy' => 'category', 'slug' => 'alternative', 'name' => 'Alternative parent', 'parent' => ''],
            ['taxonomy' => 'post_tag', 'slug' => 'child', 'name' => 'Independent tag', 'parent' => ''],
        ];

        self::assertSame(
            array_slice($terms, 0, 4),
            ImportWordPressTaxonomies::referencedDefinitions($terms, [['taxonomy' => 'category', 'slug' => 'child']]),
        );
    }

    public function testPlansAndCreatesHierarchyThenAssignsTermsIdempotently(): void
    {
        $repository = new InMemoryTaxonomyRepository();
        $entries = new InMemoryEntryRepository();
        $entryId = EntryId::new();
        $entries->add(Entry::create($entryId, 'type', 'Post', new EntrySlug('post'), [], new \DateTimeImmutable()));
        $memberships = new EntryTerms($entries, $repository);
        $import = new ImportWordPressTaxonomies($repository, $memberships);

        $terms = [
            ['taxonomy' => 'category', 'slug' => 'child', 'name' => 'Child', 'parent' => 'parent'],
            ['taxonomy' => 'category', 'slug' => 'parent', 'name' => 'Parent', 'parent' => ''],
            ['taxonomy' => 'post_tag', 'slug' => 'label', 'name' => 'Label', 'parent' => ''],
        ];
        $references = [
            ['taxonomy' => 'category', 'slug' => 'child'],
            ['taxonomy' => 'post_tag', 'slug' => 'label'],
            ['taxonomy' => 'post_tag', 'slug' => 'label'],
        ];
        $plan = $import($terms, [152 => $references], false);
        self::assertSame(['planned', 'planned', 'planned'], array_column($plan['terms'], 'state'));
        self::assertSame(['planned', 'planned'], array_column($plan['assignments'], 'state'));
        self::assertSame(['152', '152'], array_column($plan['assignments'], 'entryId'));
        $this->assertEmptyCatalog($repository);

        $result = $import($terms, [$entryId->value => $references], true);
        self::assertSame(['imported', 'imported', 'imported'], array_column($result['terms'], 'state'));
        self::assertSame(['imported', 'imported'], array_column($result['assignments'], 'state'));
        $vocabularies = array_column($repository->allTaxonomies(), null, 'slug');
        $category = $vocabularies['wordpress-categories'];
        self::assertTrue($category->hierarchical);
        self::assertFalse($vocabularies['wordpress-tags']->hierarchical);
        $created = array_column($repository->terms($category->id), null, 'slug');
        self::assertSame($created['parent']->id, $created['child']->parentId);
        self::assertSame(['child', 'label'], array_column($memberships->get($entryId->value), 'slug'));

        $terms[0]['name'] = 'Changed name';
        $replay = $import($terms, [$entryId->value => $references], true);
        self::assertSame(['existing', 'existing', 'existing'], array_column($replay['terms'], 'state'));
        self::assertSame(['existing', 'existing'], array_column($replay['assignments'], 'state'));
        self::assertSame('Child', $repository->getTerm($created['child']->id)?->name);
    }

    public function testReportsConflictingTermsAndInvalidHierarchyWithoutChangingTheCatalog(): void
    {
        $repository = new InMemoryTaxonomyRepository();
        $manage = new ManageTaxonomy($repository);
        $categories = $manage->createTaxonomy('Existing categories', 'wordpress-categories', true);
        $existing = $manage->createTerm($categories->id, 'Existing label', 'existing', null);
        $manage->createTaxonomy('Existing hierarchical tags', 'wordpress-tags', true);
        $import = new ImportWordPressTaxonomies($repository, new EntryTerms(new InMemoryEntryRepository(), $repository));

        $result = $import([
            ['taxonomy' => 'category', 'slug' => 'root', 'name' => 'Root', 'parent' => ''],
            ['taxonomy' => 'category', 'slug' => 'existing', 'name' => 'Edited label', 'parent' => 'root'],
            ['taxonomy' => 'category', 'slug' => 'cycle-a', 'name' => 'A', 'parent' => 'cycle-b'],
            ['taxonomy' => 'category', 'slug' => 'cycle-b', 'name' => 'B', 'parent' => 'cycle-a'],
            ['taxonomy' => 'category', 'slug' => 'orphan', 'name' => 'Orphan', 'parent' => 'missing'],
            ['taxonomy' => 'post_tag', 'slug' => 'tag', 'name' => 'Tag', 'parent' => ''],
        ], [5 => [['taxonomy' => 'category', 'slug' => 'existing']]], false);

        $states = array_column($result['terms'], 'state', 'slug');
        self::assertSame('planned', $states['root']);
        self::assertSame('conflict', $states['existing']);
        self::assertSame('invalid', $states['orphan']);
        self::assertSame('invalid', $states['cycle-a']);
        self::assertSame('invalid', $states['cycle-b']);
        self::assertSame('conflict', $states['tag']);
        self::assertSame(['missing_term'], array_column($result['assignments'], 'state'));
        self::assertCount(1, $repository->terms($categories->id));
        self::assertSame('Existing label', $repository->getTerm($existing->terms[0]['id'])?->name);
    }

    public function testMapsEncodedAndLiteralUnicodeSlugsToStableTerms(): void
    {
        $repository = new InMemoryTaxonomyRepository();
        $entries = new InMemoryEntryRepository();
        $entryId = EntryId::new();
        $entries->add(Entry::create($entryId, 'type', 'Post', new EntrySlug('post'), [], new \DateTimeImmutable()));
        $memberships = new EntryTerms($entries, $repository);
        $import = new ImportWordPressTaxonomies($repository, $memberships);
        $sourceSlug = rawurlencode('новости');

        $result = $import([
            ['taxonomy' => 'category', 'slug' => 'child', 'name' => 'Child', 'parent' => 'новости'],
            ['taxonomy' => 'category', 'slug' => $sourceSlug, 'name' => 'Новости', 'parent' => ''],
            ['taxonomy' => 'category', 'slug' => 'bad%3Fslug', 'name' => 'Invalid', 'parent' => ''],
        ], [$entryId->value => [['taxonomy' => 'category', 'slug' => 'новости']]], true);

        self::assertSame(['imported', 'imported', 'invalid'], array_column($result['terms'], 'state'));
        self::assertSame($sourceSlug, $result['terms'][1]['slug']);
        self::assertSame(['imported'], array_column($result['assignments'], 'state'));
        $term = $memberships->get($entryId->value)[0];
        self::assertMatchesRegularExpression('/^wp-[a-f0-9]{32}$/', $term['slug']);
        $child = array_column($repository->terms($term['taxonomyId']), null, 'slug')['child'];
        self::assertSame($term['id'], $child->parentId);

        $replay = $import([
            ['taxonomy' => 'category', 'slug' => 'новости', 'name' => 'Новости', 'parent' => ''],
        ], [], true);
        self::assertSame(['existing'], array_column($replay['terms'], 'state'));
    }

    private function assertEmptyCatalog(InMemoryTaxonomyRepository $repository): void
    {
        self::assertSame([], $repository->allTaxonomies());
    }
}
