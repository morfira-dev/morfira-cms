<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\EntryRevision;

final readonly class EntryRevisionView
{
    /** @param array<string,string|int|float|bool|list<string>|null> $values */
    public function __construct(public int $revision, public string $title, public string $slug, public array $values, public string $createdAt)
    {
    }

    public static function fromRevision(EntryRevision $revision): self
    {
        return new self($revision->revision, $revision->title, $revision->slug->value, $revision->values, $revision->createdAt->format(DATE_ATOM));
    }

    /** @return array{revision:int,title:string,slug:string,values:array<string,string|int|float|bool|list<string>|null>|\stdClass,createdAt:string} */
    public function toArray(): array
    {
        return ['revision' => $this->revision, 'title' => $this->title, 'slug' => $this->slug, 'values' => [] === $this->values ? new \stdClass() : $this->values, 'createdAt' => $this->createdAt];
    }
}
