<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SiteSettingsRepository;

final readonly class UpdateSiteSettings
{
    public function __construct(private SiteSettingsRepository $settings)
    {
    }

    public function __invoke(string $siteName, string $defaultLocale, string $timezone, int $expectedVersion): SiteSettingsView
    {
        $current = $this->settings->get();
        $updated = $current->revise($siteName, $defaultLocale, $timezone, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->settings->save($updated, $expectedVersion);

        return SiteSettingsView::fromSettings($updated);
    }
}
