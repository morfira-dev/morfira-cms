<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

final readonly class PublishedEntry
{
    /** @param array{id:string,name:string,apiName:string,version:int,fields:list<array{id:string,key:string,label:string,type:string,required:bool}>} $contentType
     * @param array<string,string|int|float|bool|null> $values
     * @param list<array{taxonomyId:string,taxonomySlug:string,termId:string,termSlug:string,name:string}> $taxonomies
     */
    public function __construct(public string $snapshotId, public string $entryId, public int $sourceVersion, public array $contentType, public string $title, public string $slug, public array $values, public array $taxonomies, public \DateTimeImmutable $publishedAt)
    {
    }
}
