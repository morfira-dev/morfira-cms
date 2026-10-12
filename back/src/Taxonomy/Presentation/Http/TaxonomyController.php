<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Presentation\Http;

use Cms\Content\Domain\EntryNotFound;
use Cms\Taxonomy\Application\EntryTerms;
use Cms\Taxonomy\Application\ManageTaxonomy;
use Cms\Taxonomy\Application\TaxonomyView;
use Cms\Taxonomy\Domain\TaxonomyConflict;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class TaxonomyController
{
    public function __construct(private ManageTaxonomy $manage, private EntryTerms $entryTerms) {}

    #[Route('/api/v1/admin/taxonomies',name:'taxonomy.list',methods:['GET'])]
    public function list():JsonResponse{return new JsonResponse(['items'=>array_map(fn(TaxonomyView $v)=>$v->toArray(),$this->manage->list())]);}

    #[Route('/api/v1/admin/taxonomies',name:'taxonomy.create',methods:['POST'])]
    public function create(Request $r):JsonResponse
    {
        $p=$r->toArray(); if(array_diff(array_keys($p),['name','slug','hierarchical'])!==[]||!is_string($p['name']??null)||!is_string($p['slug']??null)||!is_bool($p['hierarchical']??null))return new JsonResponse(['error'=>'Invalid taxonomy payload.'],422);
        try{return new JsonResponse($this->manage->createTaxonomy($p['name'],$p['slug'],$p['hierarchical'])->toArray(),201);}catch(TaxonomyConflict $e){return new JsonResponse(['error'=>$e->getMessage()],409);}catch(\InvalidArgumentException $e){return new JsonResponse(['error'=>$e->getMessage()],422);}
    }

    #[Route('/api/v1/admin/taxonomies/{id}/terms',name:'taxonomy.terms.create',methods:['POST'])]
    public function createTerm(string $id,Request $r):JsonResponse
    {
        $p=$r->toArray();$parent=$p['parentId']??null;if(array_diff(array_keys($p),['name','slug','parentId'])!==[]||!is_string($p['name']??null)||!is_string($p['slug']??null)||($parent!==null&&!is_string($parent)))return new JsonResponse(['error'=>'Invalid term payload.'],422);
        try{return new JsonResponse($this->manage->createTerm($id,$p['name'],$p['slug'],$parent)->toArray(),201);}catch(\OutOfBoundsException){return new JsonResponse(['error'=>'Taxonomy not found.'],404);}catch(TaxonomyConflict $e){return new JsonResponse(['error'=>$e->getMessage()],409);}catch(\InvalidArgumentException $e){return new JsonResponse(['error'=>$e->getMessage()],422);}
    }

    #[Route('/api/v1/admin/entries/{id}/terms',name:'taxonomy.entries.get',methods:['GET'])]
    public function getEntryTerms(string $id):JsonResponse{try{return new JsonResponse(['items'=>$this->entryTerms->get($id)]);}catch(EntryNotFound){return new JsonResponse(['error'=>'Entry not found.'],404);}catch(\InvalidArgumentException $e){return new JsonResponse(['error'=>$e->getMessage()],422);}}

    #[Route('/api/v1/admin/entries/{id}/terms',name:'taxonomy.entries.put',methods:['PUT'])]
    public function putEntryTerms(string $id,Request $r):JsonResponse
    {
        $p=$r->toArray();$ids=$p['termIds']??null;if(array_diff(array_keys($p),['termIds'])!==[]||!is_array($ids)||!array_is_list($ids))return new JsonResponse(['error'=>'termIds must be a list of term UUIDs.'],422);
        $termIds=[];foreach($ids as $termId){if(!is_string($termId))return new JsonResponse(['error'=>'termIds must be a list of term UUIDs.'],422);$termIds[]=$termId;}
        try{return new JsonResponse(['items'=>$this->entryTerms->replace($id,$termIds)]);}catch(EntryNotFound|\OutOfBoundsException){return new JsonResponse(['error'=>'Entry or term not found.'],404);}catch(\InvalidArgumentException $e){return new JsonResponse(['error'=>$e->getMessage()],422);}
    }
}
