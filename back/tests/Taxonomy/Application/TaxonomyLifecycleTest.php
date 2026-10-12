<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Content\Application\CreateEntry;
use Cms\Publishing\Application\ListPublishedEntries;
use Cms\Publishing\Application\PublishEntry;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Taxonomy\Application\EntryTerms;
use Cms\Taxonomy\Application\ManageTaxonomy;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Publishing\InMemoryPublishedEntryRepository;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use Cms\Tests\Taxonomy\InMemoryTaxonomyRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TaxonomyLifecycleTest extends TestCase
{
    #[Test]
    public function assigned_terms_are_snapshotted_and_filter_public_entries():void
    {
        $repo=new InMemoryTaxonomyRepository();$manage=new ManageTaxonomy($repo);
        $taxonomy=$manage->createTaxonomy('Topics','topics',true);
        $withTerm=$manage->createTerm($taxonomy->id,'Architecture','architecture',null);
        $term=$withTerm->terms[0];
        $types=new InMemoryContentTypeRepository();$type=(new CreateContentType($types))('Article','articles',[]);$definitions=new GetContentTypeDefinition($types);$validator=new ValidateEntryValues($definitions);
        $entries=new InMemoryEntryRepository();$entry=(new CreateEntry($entries,$validator))($type->id,'First','first',[]);
        $entryTerms=new EntryTerms($entries,$repo);$entryTerms->replace($entry->id,[$term['id']]);
        $published=new InMemoryPublishedEntryRepository();(new PublishEntry($entries,$published,$definitions,$validator,$entryTerms))($entry->id,1);
        $page=(new ListPublishedEntries($published))('articles',20,0,'topics','architecture');
        self::assertSame(1,$page['total']);self::assertSame('architecture',$page['items'][0]->taxonomies[0]['termSlug']);
        $entryTerms->replace($entry->id,[]);
        self::assertSame(1,(new ListPublishedEntries($published))('articles',20,0,'topics','architecture')['total']);
    }

    #[Test]
    public function flat_taxonomies_reject_parent_terms():void
    {
        $repo=new InMemoryTaxonomyRepository();$manage=new ManageTaxonomy($repo);$taxonomy=$manage->createTaxonomy('Tags','tags',false);$term=$manage->createTerm($taxonomy->id,'PHP','php',null)->terms[0];
        $this->expectException(\InvalidArgumentException::class);$manage->createTerm($taxonomy->id,'Symfony','symfony',$term['id']);
    }
}
