<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

final readonly class SiteSettings
{
    public function __construct(
        public string $siteName,
        public string $defaultLocale,
        public string $timezone,
        public int $version,
        public \DateTimeImmutable $updatedAt,
    ) {
        $nameLength = mb_strlen(trim($siteName));
        if ($nameLength < 1 || $nameLength > 120 || $siteName !== trim($siteName)) {
            throw new \InvalidArgumentException('Site name must contain between 1 and 120 trimmed characters.');
        }
        if (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $defaultLocale) !== 1) {
            throw new \InvalidArgumentException('Default locale must be a normalized language tag such as en or en-US.');
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException('Timezone must be a recognized IANA identifier.');
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('Settings version must be positive.');
        }
    }

    public function revise(string $siteName, string $defaultLocale, string $timezone, \DateTimeImmutable $updatedAt): self
    {
        return new self(trim($siteName), $defaultLocale, $timezone, $this->version + 1, $updatedAt);
    }
}
