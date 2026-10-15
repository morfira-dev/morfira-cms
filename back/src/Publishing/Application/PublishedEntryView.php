<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\PublishedEntry;

final readonly class PublishedEntryView
{
    /** @param array{id:string,name:string,apiName:string,version:int,fields:list<array{id:string,key:string,label:string,type:string,required:bool,targetContentTypeId?:?string,multiple?:bool}>} $contentType
     * @param array<string,string|int|float|bool|list<string>|null> $values
     * @param list<array{taxonomyId:string,taxonomySlug:string,termId:string,termSlug:string,name:string}> $taxonomies
     */
    public function __construct(public string $snapshotId, public string $entryId, public int $sourceVersion, public array $contentType, public string $title, public string $slug, public array $values, public array $taxonomies, public string $publishedAt)
    {
    }

    public static function fromEntry(PublishedEntry $e): self
    {
        return new self($e->snapshotId, $e->entryId, $e->sourceVersion, $e->contentType, $e->title, $e->slug, $e->values, $e->taxonomies, $e->publishedAt->format(DATE_ATOM));
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['snapshotId' => $this->snapshotId, 'entryId' => $this->entryId, 'sourceVersion' => $this->sourceVersion, 'contentType' => $this->contentType, 'title' => $this->title, 'slug' => $this->slug, 'values' => [] === $this->values ? new \stdClass() : $this->values, 'taxonomies' => $this->taxonomies, 'publishedAt' => $this->publishedAt];
    }
}
