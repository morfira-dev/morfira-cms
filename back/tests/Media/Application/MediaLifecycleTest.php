<?php

declare(strict_types=1);

namespace Cms\Tests\Media\Application;

use Cms\Content\Application\ContentMediaUsage;
use Cms\Content\Application\ContentMediaUsageLookup;
use Cms\Media\Application\DeleteMedia;
use Cms\Media\Application\ImageProcessor;
use Cms\Media\Application\ProcessedImage;
use Cms\Media\Application\UpdateMediaMetadata;
use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaAssetInUse;
use Cms\Media\Domain\MediaVariant;
use Cms\Publishing\Application\PublishedMediaUsage;
use Cms\Publishing\Application\PublishedMediaUsageLookup;
use Cms\Tests\Media\InMemoryMediaAssetRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MediaLifecycleTest extends TestCase
{
    #[Test]
    public function it_normalizes_and_persists_organization_metadata(): void
    {
        $repository = new InMemoryMediaAssetRepository();
        $asset = self::asset();
        $repository->add($asset);

        $view = (new UpdateMediaMetadata($repository))($asset->id->value, ' Updated alt ', ' Homepage ', ['Hero', 'hero', ' launch-card ']);

        self::assertSame('Updated alt', $view->defaultAlt);
        self::assertSame('Homepage', $view->collection);
        self::assertSame(['hero', 'launch-card'], $view->tags);
        self::assertSame($view->tags, $repository->get($asset->id)?->tags);
    }

    #[Test]
    public function it_refuses_referenced_assets_and_removes_unused_files(): void
    {
        $repository = new InMemoryMediaAssetRepository();
        $asset = self::asset();
        $repository->add($asset);
        $processor = new RemovingImageProcessor();
        $contentUsage = new ContentMediaUsage(new FixedContentUsage(['page revision']));
        $publishedUsage = new PublishedMediaUsage(new FixedPublishedUsage([]));
        $delete = new DeleteMedia($repository, $processor, $contentUsage, $publishedUsage);

        try {
            $delete($asset->id->value);
            self::fail('Referenced media must not be deleted.');
        } catch (MediaAssetInUse $exception) {
            self::assertSame(['page revision'], $exception->usages);
            self::assertNotNull($repository->get($asset->id));
            self::assertFalse($processor->removed);
        }

        $delete = new DeleteMedia(
            $repository,
            $processor,
            new ContentMediaUsage(new FixedContentUsage([])),
            $publishedUsage,
        );
        $delete($asset->id->value);

        self::assertNull($repository->get($asset->id));
        self::assertTrue($processor->removed);
    }

    private static function asset(): MediaAsset
    {
        $id = MediaAssetId::fromString('0199b348-1d8f-4e2f-8672-9b8356792e42');

        return MediaAsset::create(
            $id,
            'hero.png',
            1200,
            800,
            1234,
            'Hero',
            [new MediaVariant(1200, 800, 1234, sprintf('/media/%s/1200w.webp', $id->value))],
            new \DateTimeImmutable('2026-10-12T10:00:00+00:00'),
        );
    }
}

final readonly class FixedContentUsage implements ContentMediaUsageLookup
{
    /** @param list<string> $usages */
    public function __construct(private array $usages) {}

    public function references(string $assetId): array { return $this->usages; }
}

final readonly class FixedPublishedUsage implements PublishedMediaUsageLookup
{
    /** @param list<string> $usages */
    public function __construct(private array $usages) {}

    public function references(string $assetId): array { return $this->usages; }
}

final class RemovingImageProcessor implements ImageProcessor
{
    public bool $removed = false;

    public function process(string $sourcePath, MediaAssetId $assetId): ProcessedImage
    {
        throw new \LogicException('Not used by this test.');
    }

    public function remove(MediaAssetId $assetId): void
    {
        $this->removed = true;
    }
}
