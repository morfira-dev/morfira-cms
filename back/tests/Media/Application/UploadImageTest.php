<?php

declare(strict_types=1);

namespace Cms\Tests\Media\Application;

use Cms\Media\Application\ImageProcessor;
use Cms\Media\Application\ProcessedImage;
use Cms\Media\Application\PrivateImageProcessor;
use Cms\Media\Application\UploadImage\UploadImage;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaVariant;
use Cms\Tests\Media\InMemoryMediaAssetRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UploadImageTest extends TestCase
{
    private string $sourcePath;

    protected function setUp(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'cms-media-test-');
        self::assertIsString($sourcePath);
        file_put_contents($sourcePath, 'valid test payload');
        $this->sourcePath = $sourcePath;
    }

    protected function tearDown(): void
    {
        if (is_file($this->sourcePath)) {
            unlink($this->sourcePath);
        }
    }

    #[Test]
    public function it_persists_an_immutable_processed_asset(): void
    {
        $repository = new InMemoryMediaAssetRepository();
        $processor = new FakeImageProcessor();
        $upload = new UploadImage($repository, $processor);

        $view = $upload($this->sourcePath, ' landscape.PNG ', ' A mountain ');

        self::assertSame('landscape.PNG', $view->originalName);
        self::assertSame('image/webp', $view->mimeType);
        self::assertSame(1200, $view->width);
        self::assertSame(800, $view->height);
        self::assertSame(3210, $view->size);
        self::assertSame('A mountain', $view->defaultAlt);
        self::assertSame([[
            'url' => sprintf('/media/%s/1200w.webp', $view->id),
            'width' => 1200,
            'height' => 800,
        ]], $view->variants);
        self::assertCount(1, $repository->items);
        self::assertFalse($processor->removed);
    }

    #[Test]
    public function it_processes_private_images_only_in_private_storage(): void
    {
        $repository = new InMemoryMediaAssetRepository();
        $public = new FakeImageProcessor();
        $private = $this->createMock(PrivateImageProcessor::class);
        $private->expects(self::once())->method('process')->willReturnCallback(
            static fn (string $path, MediaAssetId $id): ProcessedImage => new ProcessedImage(480, 240, 10, [
                new MediaVariant(480, 240, 10, MediaVariant::urlFor($id, 480, true)),
            ]),
        );
        $upload = new UploadImage($repository, $public, $private);

        $view = $upload($this->sourcePath, 'private.png', '', true);

        self::assertFalse($public->processed);
        self::assertTrue($view->isPrivate);
        self::assertTrue($repository->get(MediaAssetId::fromString($view->id))?->isPrivate);
        self::assertSame('/api/v1/admin/media/'.$view->id.'/variants/480w.webp', $view->variants[0]['url']);
    }

    #[Test]
    public function it_removes_processed_files_when_persistence_fails(): void
    {
        $repository = new InMemoryMediaAssetRepository();
        $repository->failOnAdd = true;
        $processor = new FakeImageProcessor();
        $upload = new UploadImage($repository, $processor);

        try {
            $upload($this->sourcePath, 'image.jpg', '');
            self::fail('An infrastructure failure was expected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Persistence failed.', $exception->getMessage());
        }

        self::assertTrue($processor->removed);
    }

    #[Test]
    public function it_rejects_oversized_alt_text_before_processing(): void
    {
        $repository = new InMemoryMediaAssetRepository();
        $processor = new FakeImageProcessor();
        $upload = new UploadImage($repository, $processor);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Default alternative text must not exceed 500 characters.');

        try {
            $upload($this->sourcePath, 'image.jpg', str_repeat('x', 501));
        } finally {
            self::assertFalse($processor->processed);
        }
    }
}

final class FakeImageProcessor implements ImageProcessor
{
    public bool $processed = false;
    public bool $removed = false;

    public function process(string $sourcePath, MediaAssetId $assetId): ProcessedImage
    {
        $this->processed = true;

        return new ProcessedImage(1200, 800, 3210, [
            new MediaVariant(1200, 800, 3210, sprintf('/media/%s/1200w.webp', $assetId->value)),
        ]);
    }

    public function remove(MediaAssetId $assetId): void
    {
        $this->removed = true;
    }
}
