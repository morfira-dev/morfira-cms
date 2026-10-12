<?php

declare(strict_types=1);

namespace Cms\Identity\Presentation\Http;

use Cms\Identity\Application\ChangeUserRole;
use Cms\Identity\Application\CreateUser;
use Cms\Identity\Application\DeleteUser;
use Cms\Identity\Application\ListUsers;
use Cms\Identity\Application\UserView;
use Cms\Identity\Domain\LastOwnerRequired;
use Cms\Identity\Domain\SelfManagementForbidden;
use Cms\Identity\Domain\UserEmailAlreadyExists;
use Cms\Identity\Domain\UserNotFound;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class UserController
{
    public function __construct(
        private ListUsers $listUsers,
        private CreateUser $createUser,
        private ChangeUserRole $changeUserRole,
        private DeleteUser $deleteUser,
        private Security $security,
    ) {
    }

    #[Route('/api/v1/admin/users', name: 'identity.users.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map(static fn (UserView $user): array => $user->toArray(), ($this->listUsers)()),
        ]);
    }

    #[Route('/api/v1/admin/users', name: 'identity.users.create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $unexpected = array_diff(array_keys($payload), ['email', 'password', 'role']);
        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;
        $role = $payload['role'] ?? null;
        if ($unexpected !== [] || !is_string($email) || !is_string($password) || !is_string($role)) {
            return new JsonResponse(['error' => 'Email, password and role are required strings.'], 422);
        }

        try {
            return new JsonResponse(($this->createUser)($email, $password, $role)->toArray(), 201);
        } catch (UserEmailAlreadyExists $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/users/{id}', name: 'identity.users.update', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $unexpected = array_diff(array_keys($payload), ['role']);
        $role = $payload['role'] ?? null;
        if ($unexpected !== [] || !is_string($role)) {
            return new JsonResponse(['error' => 'Role must be a string.'], 422);
        }

        try {
            return new JsonResponse(($this->changeUserRole)($this->actorId(), $id, $role)->toArray());
        } catch (UserNotFound $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (LastOwnerRequired|SelfManagementForbidden $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/users/{id}', name: 'identity.users.delete', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['DELETE'])]
    public function delete(string $id): Response
    {
        try {
            ($this->deleteUser)($this->actorId(), $id);
        } catch (UserNotFound $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (LastOwnerRequired|SelfManagementForbidden $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function actorId(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('User administration requires an authenticated CMS user.');
        }

        return $user->id;
    }
}
