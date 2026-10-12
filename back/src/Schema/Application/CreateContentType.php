<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

use Cms\Schema\Domain\ContentType;
use Cms\Schema\Domain\ContentTypeId;
use Cms\Schema\Domain\ContentTypeKey;
use Cms\Schema\Domain\ContentTypeRepository;
use Cms\Schema\Domain\FieldDefinition;

final readonly class CreateContentType
{
    public function __construct(private ContentTypeRepository $types)
    {
    }

    /** @param list<array<string,mixed>> $fields */
    public function __invoke(string $name, string $key, array $fields): ContentTypeView
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $type = ContentType::create(ContentTypeId::new(), $name, new ContentTypeKey($key), array_map(FieldDefinition::fromArray(...), $fields), $now);
        $this->types->add($type);

        return ContentTypeView::fromType($type);
    }
}
