<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\Entry;

final readonly class EntryView
{
    /** @param array<string,string|int|float|bool|null> $values */
    public function __construct(public string $id, public string $contentTypeId, public string $title, public string $slug, public array $values, public int $version, public string $createdAt, public string $updatedAt)
    {
    }

    public static function fromEntry(Entry $e): self
    {
        return new self($e->id->value, $e->contentTypeId, $e->title, $e->slug->value, $e->values, $e->version, $e->createdAt->format(DATE_ATOM), $e->updatedAt->format(DATE_ATOM));
    }

    /** @return array{id:string,contentTypeId:string,title:string,slug:string,values:array<string,string|int|float|bool|null>|\stdClass,version:int,createdAt:string,updatedAt:string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'contentTypeId' => $this->contentTypeId, 'title' => $this->title, 'slug' => $this->slug, 'values' => [] === $this->values ? new \stdClass() : $this->values, 'version' => $this->version, 'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
