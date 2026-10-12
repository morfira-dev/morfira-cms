<?php

declare(strict_types=1);

namespace Cms\Settings\Presentation\Http;

use Cms\Settings\Application\GetSiteSettings;
use Cms\Settings\Application\UpdateSiteSettings;
use Cms\Settings\Domain\SiteSettingsVersionConflict;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SiteSettingsController
{
    public function __construct(private GetSiteSettings $getSettings, private UpdateSiteSettings $updateSettings)
    {
    }

    #[Route('/api/v1/admin/settings', name: 'settings.get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse(($this->getSettings)()->toArray());
    }

    #[Route('/api/v1/admin/settings', name: 'settings.update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $keys = array_keys($payload);
        sort($keys);
        if ($keys !== ['defaultLocale', 'siteName', 'timezone', 'version']
            || !is_string($payload['siteName']) || !is_string($payload['defaultLocale'])
            || !is_string($payload['timezone']) || !is_int($payload['version'])) {
            return new JsonResponse(['error' => 'Invalid site settings payload.'], 422);
        }

        try {
            return new JsonResponse(($this->updateSettings)($payload['siteName'], $payload['defaultLocale'], $payload['timezone'], $payload['version'])->toArray());
        } catch (SiteSettingsVersionConflict $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }
}
