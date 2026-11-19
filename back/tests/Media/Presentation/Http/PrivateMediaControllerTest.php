<?php

declare(strict_types=1);

namespace Cms\Tests\Media\Presentation\Http;

use Cms\Identity\Infrastructure\Security\SecurityUser;
use Cms\Identity\Infrastructure\Security\SecurityUserProvider;
use Cms\Media\Application\PrivateImageProcessor;
use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaVariant;
use Cms\Media\Infrastructure\Image\LocalPrivateImageProcessor;
use Cms\Media\Infrastructure\Persistence\DoctrineMediaAssetRepository;
use Cms\Tests\Media\InMemoryMediaAssetRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class PrivateMediaControllerTest extends WebTestCase
{
    public function testAnonymousRequestsAreRejectedBeforeReadingTheAsset(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/v1/admin/media/12345678-1234-4123-8123-123456789012/variants/480w.webp');
        self::assertResponseStatusCodeSame(401);
    }

    public function testEditorReceivesNoStoreWebpForAKnownPrivateVariant(): void
    {
        $client = self::createClient();
        $user = new SecurityUser('11111111-1111-4111-8111-111111111111', 'editor@example.test', 'unused-test-hash', ['ROLE_EDITOR']);
        $provider = $this->createStub(UserProviderInterface::class);
        $provider->method('refreshUser')->willReturn($user);
        $provider->method('supportsClass')->willReturnCallback(static fn (string $class): bool => $class === SecurityUser::class);
        self::getContainer()->set(SecurityUserProvider::class, $provider);
        $client->loginUser($user);

        $id = MediaAssetId::new();
        $variant = new MediaVariant(480, 240, 10, MediaVariant::urlFor($id, 480, true));
        $repository = new InMemoryMediaAssetRepository();
        $repository->add(MediaAsset::create($id, 'fixture.webp', 480, 240, 10, '', [$variant], new \DateTimeImmutable(), true));
        self::getContainer()->set(DoctrineMediaAssetRepository::class, $repository);

        $file = tmpfile();
        self::assertIsResource($file);
        try {
            fwrite($file, 'fixture');
            $path = stream_get_meta_data($file)['uri'] ?? null;
            self::assertIsString($path);
            $images = $this->createMock(PrivateImageProcessor::class);
            $images->expects(self::once())->method('variantPath')->with($id, '480w.webp')->willReturn($path);
            self::getContainer()->set(LocalPrivateImageProcessor::class, $images);

            $client->request('GET', $variant->url);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', 'image/webp');
            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
            $response = $client->getResponse();
            self::assertInstanceOf(BinaryFileResponse::class, $response);
            self::assertTrue($response->headers->hasCacheControlDirective('private'));
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
            self::assertSame($path, $response->getFile()->getPathname());
        } finally {
            fclose($file);
        }
    }
}
