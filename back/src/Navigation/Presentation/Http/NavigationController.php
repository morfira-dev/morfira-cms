<?php

declare(strict_types=1);

namespace Cms\Navigation\Presentation\Http;

use Cms\Navigation\Application\CreateNavigationMenu\CreateNavigationMenu;
use Cms\Navigation\Application\GetNavigationMenu\GetNavigationMenu;
use Cms\Navigation\Application\ListNavigationMenus\ListNavigationMenus;
use Cms\Navigation\Application\NavigationMenuView;
use Cms\Navigation\Application\PublishNavigationMenu\PublishNavigationMenu;
use Cms\Navigation\Application\UpdateNavigationMenu\UpdateNavigationMenu;
use Cms\Navigation\Domain\NavigationCodeAlreadyExists;
use Cms\Navigation\Domain\NavigationMenuNotFound;
use Cms\Navigation\Domain\NavigationVersionConflict;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class NavigationController
{
    public function __construct(
        private CreateNavigationMenu $createMenu,
        private GetNavigationMenu $getMenu,
        private ListNavigationMenus $listMenus,
        private UpdateNavigationMenu $updateMenu,
        private PublishNavigationMenu $publishMenu,
    ) {}

    #[Route('/api/v1/admin/navigation', name: 'navigation.menus.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['items' => array_map(static fn (NavigationMenuView $menu): array => $menu->toArray(), ($this->listMenus)())]);
    }

    #[Route('/api/v1/admin/navigation', name: 'navigation.menus.create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $name = $payload['name'] ?? null;
        $code = $payload['code'] ?? null;
        $items = $payload['items'] ?? null;
        if (!is_string($name) || !is_string($code) || !is_array($items) || !array_is_list($items)) { return new JsonResponse(['error' => 'Fields "name", "code" and "items" are required.'], 422); }
        $itemPayloads = [];
        foreach ($items as $item) {
            if (!is_array($item)) { return new JsonResponse(['error' => 'Every navigation item must be an object.'], 422); }
            /** @var array<string, mixed> $item */
            $itemPayloads[] = $item;
        }
        try { $menu = ($this->createMenu)($name, $code, $itemPayloads); }
        catch (NavigationCodeAlreadyExists $exception) { return new JsonResponse(['error' => $exception->getMessage()], 409); }
        catch (\InvalidArgumentException $exception) { return new JsonResponse(['error' => $exception->getMessage()], 422); }
        return new JsonResponse($menu->toArray(), 201);
    }

    #[Route('/api/v1/admin/navigation/{id}', name: 'navigation.menus.get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        try { return new JsonResponse(($this->getMenu)($id)->toArray()); }
        catch (NavigationMenuNotFound) { return new JsonResponse(['error' => 'Navigation menu not found.'], 404); }
        catch (\InvalidArgumentException $exception) { return new JsonResponse(['error' => $exception->getMessage()], 422); }
    }

    #[Route('/api/v1/admin/navigation/{id}', name: 'navigation.menus.update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $name = $payload['name'] ?? null;
        $code = $payload['code'] ?? null;
        $items = $payload['items'] ?? null;
        $version = $payload['version'] ?? null;
        if (!is_string($name) || !is_string($code) || !is_array($items) || !array_is_list($items) || !is_int($version)) {
            return new JsonResponse(['error' => 'Invalid navigation menu revision payload.'], 422);
        }
        $itemPayloads = [];
        foreach ($items as $item) {
            if (!is_array($item)) { return new JsonResponse(['error' => 'Every navigation item must be an object.'], 422); }
            /** @var array<string, mixed> $item */
            $itemPayloads[] = $item;
        }
        try { $menu = ($this->updateMenu)($id, $name, $code, $itemPayloads, $version); }
        catch (NavigationMenuNotFound) { return new JsonResponse(['error' => 'Navigation menu not found.'], 404); }
        catch (NavigationCodeAlreadyExists|NavigationVersionConflict $exception) { return new JsonResponse(['error' => $exception->getMessage()], 409); }
        catch (\InvalidArgumentException $exception) { return new JsonResponse(['error' => $exception->getMessage()], 422); }
        return new JsonResponse($menu->toArray());
    }

    #[Route('/api/v1/admin/navigation/{id}/publish', name: 'navigation.menus.publish', methods: ['POST'])]
    public function publish(string $id, Request $request): JsonResponse
    {
        $version = $request->toArray()['version'] ?? null;
        if (!is_int($version)) { return new JsonResponse(['error' => 'The current integer version is required.'], 422); }
        try { $menu = ($this->publishMenu)($id, $version); }
        catch (NavigationMenuNotFound) { return new JsonResponse(['error' => 'Navigation menu not found.'], 404); }
        catch (NavigationCodeAlreadyExists|NavigationVersionConflict $exception) { return new JsonResponse(['error' => $exception->getMessage()], 409); }
        catch (\InvalidArgumentException $exception) { return new JsonResponse(['error' => $exception->getMessage()], 422); }
        return new JsonResponse($menu->toArray());
    }
}
