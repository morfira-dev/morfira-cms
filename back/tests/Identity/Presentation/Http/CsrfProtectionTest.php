<?php

declare(strict_types=1);

namespace Cms\Tests\Identity\Presentation\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CsrfProtectionTest extends WebTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function protectedMutationProvider(): iterable
    {
        yield 'password rotation' => ['/api/v1/auth/password', '{"currentPassword":"current-password","newPassword":"replacement-password"}'];
        yield 'site settings' => ['/api/v1/admin/settings', '{"siteName":"Morfira","defaultLocale":"en","timezone":"UTC","version":1}'];
    }

    #[Test]
    #[DataProvider('protectedMutationProvider')]
    public function mutation_rejects_a_missing_csrf_token(string $path, string $body): void
    {
        $client = self::createClient();
        $client->request('PUT', $path, server: ['CONTENT_TYPE' => 'application/json'], content: $body);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonStringEqualsJsonString('{"error":"Invalid CSRF token."}', (string) $client->getResponse()->getContent());
    }
}
