<?php

declare(strict_types=1);

namespace Cms\Extensions\Infrastructure;

use Cms\Extensions\Application\ExtensionCatalog;
use Cms\Extensions\Application\ExtensionManifest;
use Cms\Extensions\Application\ExtensionManifestProvider;

final readonly class RegisteredExtensionCatalog implements ExtensionCatalog
{
    /** @param iterable<ExtensionManifestProvider> $providers */
    public function __construct(private iterable $providers)
    {
    }

    public function all(): array
    {
        $manifests = [];
        foreach ($this->providers as $provider) {
            $manifest = $provider->manifest();
            if (isset($manifests[$manifest->id])) {
                throw new \LogicException(sprintf('Duplicate extension identifier "%s".', $manifest->id));
            }
            $manifests[$manifest->id] = $manifest;
        }
        ksort($manifests);

        return array_values($manifests);
    }
}
