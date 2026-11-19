<?php

declare(strict_types=1);

namespace Cms\Tests\Media\Presentation\Twig;

use Cms\Media\Application\GetMediaAsset\GetMediaAsset;
use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaVariant;
use Cms\Media\Presentation\Twig\MediaExtension;
use Cms\Tests\Media\InMemoryMediaAssetRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class MediaExtensionTest extends TestCase
{
    public function testPrivateAssetsOnlyAppearInAuthorizedPreview(): void
    {
        $repository = new InMemoryMediaAssetRepository();
        $imageId = MediaAssetId::new();
        $variant = new MediaVariant(64, 48, 10, MediaVariant::urlFor($imageId, 64, true));
        $repository->add(MediaAsset::create($imageId, 'private.png', 64, 48, 10, '', [$variant], new \DateTimeImmutable(), true));

        $requests = new RequestStack();
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->expects(self::once())->method('isGranted')->with('ROLE_EDITOR')->willReturn(false);
        $twig = new MediaExtension(new GetMediaAsset($repository), $requests, $authorization);
        self::assertNull($twig->find($imageId->value));

        $request = Request::create('/published');
        $request->attributes->set('_route', 'publishing.pages.public');
        $requests->push($request);
        self::assertNull($twig->find($imageId->value));

        $request->attributes->set('_route', 'content.pages.preview');
        self::assertNull($twig->find($imageId->value));

        $editor = $this->createMock(AuthorizationCheckerInterface::class);
        $editor->expects(self::once())->method('isGranted')->with('ROLE_EDITOR')->willReturn(true);
        $preview = new MediaExtension(new GetMediaAsset($repository), $requests, $editor);
        self::assertSame($variant->url, $preview->find($imageId->value)?->variants[0]['url']);
    }
}
