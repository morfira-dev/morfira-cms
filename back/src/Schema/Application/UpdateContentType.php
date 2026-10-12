<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

use Cms\Schema\Domain\ContentTypeId;
use Cms\Schema\Domain\ContentTypeKey;
use Cms\Schema\Domain\ContentTypeNotFound;
use Cms\Schema\Domain\ContentTypeRepository;
use Cms\Schema\Domain\ContentTypeVersionConflict;
use Cms\Schema\Domain\FieldDefinition;

final readonly class UpdateContentType
{
    public function __construct(private ContentTypeRepository $types)
    {
    }

    /** @param list<array<string,mixed>> $fields */
    public function __invoke(string $id, string $name, string $key, array $fields, int $version): ContentTypeView
    {
        $type = $this->types->get(ContentTypeId::fromString($id));
        if (null === $type) {
            throw new ContentTypeNotFound();
        }
        if ($type->version !== $version) {
            throw new ContentTypeVersionConflict();
        }
        $updated = $type->revise(
            $name,
            new ContentTypeKey($key),
            array_map(FieldDefinition::fromArray(...), $fields),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        $this->types->save($updated, $version);

        return ContentTypeView::fromType($updated);
    }
}
