<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

interface PublishedMediaUsageLookup
{
    /** @return list<string> */
    public function references(string $assetId): array;
}
