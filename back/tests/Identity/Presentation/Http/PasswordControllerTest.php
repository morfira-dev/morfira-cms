<?php

declare(strict_types=1);

namespace Cms\Tests\Identity\Presentation\Http;

use Cms\Identity\Application\ChangePassword;
use Cms\Identity\Application\CreateUser;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Cms\Identity\Presentation\Http\PasswordController;
use Cms\Tests\Identity\FakePasswordHasher;
use Cms\Tests\Identity\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class PasswordControllerTest extends TestCase
{
    #[Test]
    public function it_changes_only_the_authenticated_users_password(): void
    {
        $users = new InMemoryUserRepository();
        $created = (new CreateUser($users, new FakePasswordHasher()))('owner@example.test', 'current-password', 'owner');
        $securityUser = new SecurityUser($created->id, $created->email, 'hashed:current-password', ['ROLE_OWNER', 'ROLE_EDITOR']);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($securityUser, 'main', $securityUser->getRoles()));
        $container = new class ($tokens) implements ContainerInterface {
            public function __construct(private readonly TokenStorage $tokens)
            {
            }

            public function get(string $id): mixed
            {
                return $id === 'security.token_storage' ? $this->tokens : throw new \RuntimeException('Unknown service.');
            }

            public function has(string $id): bool
            {
                return $id === 'security.token_storage';
            }
        };
        $controller = new PasswordController(new ChangePassword($users, new FakePasswordHasher()), new Security($container));
        $request = Request::create('/api/v1/auth/password', 'PUT', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'currentPassword' => 'current-password', 'newPassword' => 'replacement-password',
        ], JSON_THROW_ON_ERROR));

        self::assertSame(204, $controller->update($request)->getStatusCode());
        self::assertSame('hashed:replacement-password', $users->findById($created->id)?->passwordHash);
    }
}
