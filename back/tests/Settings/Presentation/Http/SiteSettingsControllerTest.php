<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Presentation\Http;

use Cms\Settings\Application\GetSiteSettings;
use Cms\Settings\Application\UpdateSiteSettings;
use Cms\Settings\Presentation\Http\SiteSettingsController;
use Cms\Tests\Settings\InMemorySiteSettingsRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SiteSettingsControllerTest extends TestCase
{
    #[Test]
    public function it_returns_settings_and_rejects_unexpected_mutation_fields(): void
    {
        $repository = new InMemorySiteSettingsRepository();
        $controller = new SiteSettingsController(new GetSiteSettings($repository), new UpdateSiteSettings($repository));

        self::assertSame(200, $controller->get()->getStatusCode());
        $request = Request::create('/api/v1/admin/settings', 'PUT', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'siteName' => 'Morfira', 'defaultLocale' => 'en', 'timezone' => 'UTC', 'version' => 1, 'secret' => true,
        ], JSON_THROW_ON_ERROR));
        self::assertSame(422, $controller->update($request)->getStatusCode());
    }
}
