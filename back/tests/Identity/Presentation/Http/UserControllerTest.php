<?php

declare(strict_types=1);

namespace Cms\Tests\Identity\Presentation\Http;

use Cms\Identity\Application\ChangeUserRole;
use Cms\Identity\Application\CreateUser;
use Cms\Identity\Application\DeleteUser;
use Cms\Identity\Application\ListUsers;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Cms\Identity\Presentation\Http\UserController;
use Cms\Tests\Identity\FakePasswordHasher;
use Cms\Tests\Identity\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class UserControllerTest extends TestCase
{
    #[Test]
    public function create_returns_a_safe_user_summary(): void
    {
        [$controller] = $this->controller();
        $request = self::jsonRequest('POST', '/api/v1/admin/users', [
            'email' => 'editor@example.test',
            'password' => 'a-secure-password',
            'role' => 'editor',
        ]);

        $response = $controller->create($request);
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(201, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('editor@example.test', $payload['email'] ?? null);
        self::assertSame('editor', $payload['role'] ?? null);
        self::assertArrayNotHasKey('password', $payload);
        self::assertArrayNotHasKey('passwordHash', $payload);
    }

    #[Test]
    public function create_rejects_unexpected_properties(): void
    {
        [$controller] = $this->controller();
        $request = self::jsonRequest('POST', '/api/v1/admin/users', [
            'email' => 'editor@example.test',
            'password' => 'a-secure-password',
            'role' => 'editor',
            'isAdmin' => true,
        ]);

        self::assertSame(422, $controller->create($request)->getStatusCode());
    }

    #[Test]
    public function update_maps_self_management_to_conflict(): void
    {
        [$controller, $actorId] = $this->controller();
        $request = self::jsonRequest('PUT', '/api/v1/admin/users/' . $actorId, ['role' => 'editor']);

        self::assertSame(409, $controller->update($actorId, $request)->getStatusCode());
    }

    /** @return array{UserController, string} */
    private function controller(): array
    {
        $users = new InMemoryUserRepository();
        $create = new CreateUser($users, new FakePasswordHasher());
        $actor = $create('owner@example.test', 'a-secure-password', 'owner');
        $securityUser = new SecurityUser($actor->id, $actor->email, 'hash', ['ROLE_OWNER', 'ROLE_EDITOR']);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($securityUser, 'main', $securityUser->getRoles()));
        $container = new class ($tokenStorage) implements ContainerInterface {
            public function __construct(private readonly TokenStorage $tokens)
            {
            }

            public function get(string $id): mixed
            {
                return $id === 'security.token_storage'
                    ? $this->tokens
                    : throw new \RuntimeException(sprintf('Unknown test service "%s".', $id));
            }

            public function has(string $id): bool
            {
                return $id === 'security.token_storage';
            }
        };

        return [new UserController(
            new ListUsers($users),
            $create,
            new ChangeUserRole($users),
            new DeleteUser($users),
            new Security($container),
        ), $actor->id];
    }

    /** @param array<string, mixed> $payload */
    private static function jsonRequest(string $method, string $uri, array $payload): Request
    {
        return Request::create(
            $uri,
            $method,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
