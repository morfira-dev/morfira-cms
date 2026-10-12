<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

final readonly class ContentTypeDefinition
{
    /** @param list<array{id:string,key:string,label:string,type:string,required:bool}> $fields */
    public function __construct(public string $id, public string $name, public string $apiName, public int $version, public array $fields)
    {
    }
}
