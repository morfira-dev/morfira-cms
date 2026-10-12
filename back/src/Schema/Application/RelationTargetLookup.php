<?php

declare(strict_types=1);

namespace Cms\Schema\Application;

interface RelationTargetLookup
{
    public function exists(string $entryId, string $contentTypeId): bool;
}
