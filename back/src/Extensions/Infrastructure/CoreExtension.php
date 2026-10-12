<?php

declare(strict_types=1);

namespace Cms\Extensions\Infrastructure;

use Cms\Extensions\Application\ExtensionManifest;
use Cms\Extensions\Application\ExtensionManifestProvider;
use Cms\Extensions\Application\PagePublishedContext;
use Cms\Extensions\Application\PagePublishedSubscriber;

final class CoreExtension implements ExtensionManifestProvider, PagePublishedSubscriber
{
    public function manifest(): ExtensionManifest
    {
        return new ExtensionManifest(
            'cms.morfira.core-observer',
            'Morfira publication observer',
            '1.0.0',
            'Morfira',
            1,
            ['page.published.observe'],
        );
    }

    public function onPagePublished(PagePublishedContext $context): void
    {
        // The built-in observer proves the stable hook contract without adding side effects.
    }
}
