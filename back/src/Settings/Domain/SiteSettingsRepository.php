<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

interface SiteSettingsRepository
{
    public function get(): SiteSettings;

    public function save(SiteSettings $settings, int $expectedVersion): void;
}
