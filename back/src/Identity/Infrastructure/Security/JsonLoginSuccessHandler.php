<?php

declare(strict_types=1);

namespace Cms\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final readonly class JsonLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        if (!$user instanceof SecurityUser) {
            return new JsonResponse(['error' => 'Invalid authenticated user.'], 500);
        }

        return new JsonResponse([
            'id' => $user->id,
            'email' => $user->email,
            'roles' => $user->getRoles(),
        ]);
    }
}
