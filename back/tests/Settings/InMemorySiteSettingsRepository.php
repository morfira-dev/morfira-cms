<?php

declare(strict_types=1);

namespace Cms\Tests\Settings;

use Cms\Settings\Domain\SiteSettings;
use Cms\Settings\Domain\SiteSettingsRepository;
use Cms\Settings\Domain\SiteSettingsVersionConflict;

final class InMemorySiteSettingsRepository implements SiteSettingsRepository
{
    private SiteSettings $settings;

    public function __construct()
    {
        $this->settings = new SiteSettings('Morfira CMS', 'en', 'UTC', 1, new \DateTimeImmutable('2026-10-12T00:00:00Z'));
    }

    public function get(): SiteSettings
    {
        return $this->settings;
    }

    public function save(SiteSettings $settings, int $expectedVersion): void
    {
        if ($this->settings->version !== $expectedVersion) {
            throw new SiteSettingsVersionConflict();
        }
        $this->settings = $settings;
    }
}
