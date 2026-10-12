<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SiteSettingsRepository;

final readonly class GetSiteSettings
{
    public function __construct(private SiteSettingsRepository $settings)
    {
    }

    public function __invoke(): SiteSettingsView
    {
        return SiteSettingsView::fromSettings($this->settings->get());
    }
}
