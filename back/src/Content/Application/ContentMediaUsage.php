<?php

declare(strict_types=1);

namespace Cms\Content\Application;

final readonly class ContentMediaUsage
{
    public function __construct(private ContentMediaUsageLookup $lookup)
    {
    }

    /** @return list<string> */
    public function references(string $assetId): array
    {
        return $this->lookup->references($assetId);
    }
}
