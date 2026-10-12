<?php

declare(strict_types=1);

namespace Cms\Identity\Presentation\Http;

use Cms\Identity\Application\SetupOwner\SetupOwner;
use Cms\Identity\Application\ChangeAdminLocale;
use Cms\Identity\Domain\IdentityAlreadyInstalled;
use Cms\Identity\Domain\UserRepository;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class IdentityController
{
    public function __construct(
        private UserRepository $users,
        private SetupOwner $setupOwner,
        private CsrfTokenManagerInterface $csrfTokens,
        private Security $security,
        private ChangeAdminLocale $changeAdminLocale,
    ) {
    }

    #[Route('/api/v1/setup', name: 'identity.setup.status', methods: ['GET'])]
    public function setupStatus(): JsonResponse
    {
        return new JsonResponse(['required' => !$this->users->isInstalled()]);
    }

    #[Route('/api/v1/setup', name: 'identity.setup.create', methods: ['POST'])]
    public function setup(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;

        if (!is_string($email) || !is_string($password)) {
            return new JsonResponse(['error' => 'Email and password must be strings.'], 422);
        }

        try {
            $user = ($this->setupOwner)($email, $password);
        } catch (IdentityAlreadyInstalled $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new JsonResponse([
            'id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles,
            'adminLocale' => $user->adminLocale->value,
        ], 201);
    }

    #[Route('/api/v1/auth/csrf', name: 'identity.auth.csrf', methods: ['GET'])]
    public function csrf(): JsonResponse
    {
        return new JsonResponse(['token' => $this->csrfTokens->getToken('cms_admin')->getValue()]);
    }

    #[Route('/api/v1/auth/me', name: 'identity.auth.me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            return new JsonResponse(['error' => 'Authentication required.'], 401);
        }

        return new JsonResponse([
            'id' => $user->id,
            'email' => $user->email,
            'roles' => $user->getRoles(),
            'adminLocale' => $user->adminLocale->value,
        ]);
    }

    #[Route('/api/v1/auth/preferences', name: 'identity.auth.preferences', methods: ['PUT'])]
    public function preferences(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
            return new JsonResponse(['error' => 'Authentication required.'], 401);
        }

        $payload = $request->toArray();
        $adminLocale = $payload['adminLocale'] ?? null;
        if (!is_string($adminLocale) || array_keys($payload) !== ['adminLocale']) {
            return new JsonResponse(['error' => 'Admin locale is required.'], 422);
        }

        try {
            $locale = ($this->changeAdminLocale)($user->id, $adminLocale);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        return new JsonResponse(['adminLocale' => $locale->value]);
    }

    #[Route('/api/v1/auth/login', name: 'identity.auth.login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('This request is handled by the security firewall.');
    }

    #[Route('/api/v1/auth/logout', name: 'identity.auth.logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('This request is handled by the security firewall.');
    }

}
