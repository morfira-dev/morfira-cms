<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

final readonly class PublishedMediaUsage
{
    public function __construct(private PublishedMediaUsageLookup $lookup)
    {
    }

    /** @return list<string> */
    public function references(string $assetId): array
    {
        return $this->lookup->references($assetId);
    }
}
