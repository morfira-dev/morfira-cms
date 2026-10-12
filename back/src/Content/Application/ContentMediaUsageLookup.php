<?php

declare(strict_types=1);

namespace Cms\Content\Application;

interface ContentMediaUsageLookup
{
    /** @return list<string> */
    public function references(string $assetId): array;
}
