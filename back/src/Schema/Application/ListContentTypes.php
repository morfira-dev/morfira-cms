<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

use Cms\Schema\Domain\ContentTypeRepository;

final readonly class ListContentTypes
{
    public function __construct(private ContentTypeRepository $types)
    {
    }

    /** @return list<ContentTypeView> */
    public function __invoke(): array
    {
        return array_map(ContentTypeView::fromType(...), $this->types->all());
    }
}
