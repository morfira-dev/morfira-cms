<?php

declare(strict_types=1);

namespace Cms\Extensions\Application;

interface PagePublishedSubscriber
{
    public function onPagePublished(PagePublishedContext $context): void;
}
