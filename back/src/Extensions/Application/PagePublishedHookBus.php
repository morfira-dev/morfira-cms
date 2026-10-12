<?php

declare(strict_types=1);

namespace Cms\Extensions\Application;

interface PagePublishedHookBus
{
    public function dispatch(PagePublishedContext $context): void;
}
