<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

use Cms\Schema\Domain\ContentTypeId;
use Cms\Schema\Domain\ContentTypeNotFound;
use Cms\Schema\Domain\ContentTypeRepository;

final readonly class GetContentType
{
    public function __construct(private ContentTypeRepository $types)
    {
    }

    public function __invoke(string $id): ContentTypeView
    {
        $type = $this->types->get(ContentTypeId::fromString($id));
        if (null === $type) {
            throw new ContentTypeNotFound();
        }

        return ContentTypeView::fromType($type);
    }
}
