<?php

declare(strict_types=1);

namespace Cms\Extensions\Application;

interface ExtensionCatalog
{
    /** @return list<ExtensionManifest> */
    public function all(): array;
}
