<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

final class SiteSettingsVersionConflict extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Site settings changed since they were loaded. Reload and try again.');
    }
}
