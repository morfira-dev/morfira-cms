<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

use Cms\Schema\Domain\ContentType;
use Cms\Schema\Domain\FieldDefinition;

final readonly class ContentTypeView
{
    /** @param list<array{id:string,key:string,label:string,type:string,required:bool}> $fields */
    public function __construct(public string $id, public string $name, public string $apiName, public array $fields, public int $version, public string $createdAt, public string $updatedAt)
    {
    }

    public static function fromType(ContentType $type): self
    {
        return new self($type->id->value, $type->name, $type->key->value, array_map(static fn (FieldDefinition $f): array => $f->toArray(), $type->fields), $type->version, $type->createdAt->format(DATE_ATOM), $type->updatedAt->format(DATE_ATOM));
    }

    /** @return array{id:string,name:string,apiName:string,fields:list<array{id:string,key:string,label:string,type:string,required:bool}>,version:int,createdAt:string,updatedAt:string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'apiName' => $this->apiName, 'fields' => $this->fields, 'version' => $this->version, 'createdAt' => $this->createdAt, 'updatedAt' => $this->updatedAt];
    }
}
