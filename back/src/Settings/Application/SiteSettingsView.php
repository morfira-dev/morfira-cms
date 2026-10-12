<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SiteSettings;

final readonly class SiteSettingsView
{
    public function __construct(
        public string $siteName,
        public string $defaultLocale,
        public string $timezone,
        public int $version,
        public string $updatedAt,
    ) {
    }

    public static function fromSettings(SiteSettings $settings): self
    {
        return new self($settings->siteName, $settings->defaultLocale, $settings->timezone, $settings->version, $settings->updatedAt->format(DATE_ATOM));
    }

    /** @return array{siteName: string, defaultLocale: string, timezone: string, version: int, updatedAt: string} */
    public function toArray(): array
    {
        return ['siteName' => $this->siteName, 'defaultLocale' => $this->defaultLocale, 'timezone' => $this->timezone, 'version' => $this->version, 'updatedAt' => $this->updatedAt];
    }
}
