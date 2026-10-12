<?php

declare(strict_types=1);

namespace Cms\Extensions\Application;

interface ExtensionManifestProvider
{
    public function manifest(): ExtensionManifest;
}
