<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

use Cms\Schema\Domain\ContentTypeId;
use Cms\Schema\Domain\ContentTypeKey;
use Cms\Schema\Domain\ContentTypeNotFound;
use Cms\Schema\Domain\ContentTypeRepository;
use Cms\Schema\Domain\FieldDefinition;

final readonly class GetContentTypeDefinition
{
    public function __construct(private ContentTypeRepository $types)
    {
    }

    public function byId(string $id): ContentTypeDefinition
    {
        $type = $this->types->get(ContentTypeId::fromString($id));
        if (null === $type) {
            throw new ContentTypeNotFound();
        }

        return $this->view($type);
    }

    public function byKey(string $key): ContentTypeDefinition
    {
        $type = $this->types->findByKey(new ContentTypeKey($key));
        if (null === $type) {
            throw new ContentTypeNotFound();
        }

        return $this->view($type);
    }

    private function view(\Cms\Schema\Domain\ContentType $type): ContentTypeDefinition
    {
        return new ContentTypeDefinition($type->id->value, $type->name, $type->key->value, $type->version, array_map(static fn (FieldDefinition $f): array => $f->toArray(), $type->fields));
    }
}
