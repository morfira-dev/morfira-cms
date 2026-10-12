<?php

declare(strict_types=1);

namespace Cms\Tests\Extensions\Presentation\Http;

use Cms\Identity\Infrastructure\Security\SecurityUser;
use Cms\Identity\Infrastructure\Security\SecurityUserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class ExtensionControllerTest extends WebTestCase
{
    public function test_owner_can_inspect_registered_extensions(): void
    {
        $client = self::createClient();
        $user = $this->user(['ROLE_OWNER', 'ROLE_EDITOR']);
        $this->replaceUserProvider($user);
        $client->loginUser($user);

        $client->request('GET', '/api/v1/admin/extensions');

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(1, $payload['apiVersion'] ?? null);
        $items = $payload['items'] ?? null;
        self::assertIsArray($items);
        $extension = $items[0] ?? null;
        self::assertIsArray($extension);
        self::assertSame('cms.morfira.core-observer', $extension['id'] ?? null);
        self::assertSame(['page.published.observe'], $extension['capabilities'] ?? null);
    }

    public function test_editor_cannot_inspect_registered_extensions(): void
    {
        $client = self::createClient();
        $user = $this->user(['ROLE_EDITOR']);
        $this->replaceUserProvider($user);
        $client->loginUser($user);

        $client->request('GET', '/api/v1/admin/extensions');

        self::assertResponseStatusCodeSame(403);
    }

    /** @param list<string> $roles */
    private function user(array $roles): SecurityUser
    {
        return new SecurityUser(
            '11111111-1111-4111-8111-111111111111',
            'actor@example.test',
            'unused-test-hash',
            $roles,
        );
    }

    private function replaceUserProvider(SecurityUser $user): void
    {
        self::getContainer()->set(SecurityUserProvider::class, new class ($user) implements UserProviderInterface {
            public function __construct(private readonly SecurityUser $user)
            {
            }

            public function loadUserByIdentifier(string $identifier): UserInterface
            {
                return $this->user;
            }

            public function refreshUser(UserInterface $user): UserInterface
            {
                return $this->user;
            }

            public function supportsClass(string $class): bool
            {
                return $class === SecurityUser::class;
            }
        });
    }
}
