<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Application;

use Cms\Settings\Application\GetSiteSettings;
use Cms\Settings\Application\UpdateSiteSettings;
use Cms\Settings\Domain\SiteSettingsVersionConflict;
use Cms\Tests\Settings\InMemorySiteSettingsRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SiteSettingsTest extends TestCase
{
    #[Test]
    public function it_updates_valid_settings_with_optimistic_locking(): void
    {
        $repository = new InMemorySiteSettingsRepository();
        $updated = (new UpdateSiteSettings($repository))('Morfira Studio', 'ru-RU', 'Europe/Moscow', 1);

        self::assertSame(2, $updated->version);
        self::assertSame('Morfira Studio', (new GetSiteSettings($repository))()->siteName);

        $this->expectException(SiteSettingsVersionConflict::class);
        (new UpdateSiteSettings($repository))('Stale update', 'en', 'UTC', 1);
    }
}
