<?php

declare(strict_types=1);

namespace Cms\Tests\System\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function test_it_exposes_the_system_status(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","service":"cms"}',
            (string) $client->getResponse()->getContent(),
        );
    }
}
