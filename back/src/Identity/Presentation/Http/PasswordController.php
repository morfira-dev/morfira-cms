<?php

declare(strict_types=1);

namespace Cms\Identity\Presentation\Http;

use Cms\Identity\Application\ChangePassword;
use Cms\Identity\Domain\CurrentPasswordInvalid;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PasswordController
{
    public function __construct(private ChangePassword $changePassword, private Security $security)
    {
    }

    #[Route('/api/v1/auth/password', name: 'identity.auth.password', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
            return new JsonResponse(['error' => 'Authentication required.'], 401);
        }
        $payload = $request->toArray();
        $keys = array_keys($payload);
        sort($keys);
        if ($keys !== ['currentPassword', 'newPassword'] || !is_string($payload['currentPassword']) || !is_string($payload['newPassword'])) {
            return new JsonResponse(['error' => 'Current and new passwords must be strings.'], 422);
        }

        try {
            ($this->changePassword)($user->id, $payload['currentPassword'], $payload['newPassword']);
        } catch (CurrentPasswordInvalid|\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        return new JsonResponse(null, 204);
    }
}
