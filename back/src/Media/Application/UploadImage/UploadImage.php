<?php

declare(strict_types=1);

namespace Cms\Media\Application\UploadImage;

use Cms\Media\Application\ImageProcessor;
use Cms\Media\Application\MediaAssetView;
use Cms\Media\Application\MediaTooLarge;
use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaAssetRepository;

final readonly class UploadImage
{
    public const MAX_BYTES = 10_485_760;

    public function __construct(
        private MediaAssetRepository $assets,
        private ImageProcessor $processor,
    ) {
    }

    public function __invoke(string $sourcePath, string $originalName, string $defaultAlt): MediaAssetView
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new \InvalidArgumentException('Uploaded image is not readable.');
        }

        $sourceBytes = filesize($sourcePath);
        if (!is_int($sourceBytes) || $sourceBytes < 1 || $sourceBytes > self::MAX_BYTES) {
            throw new MediaTooLarge();
        }

        if (trim(basename($originalName)) === '' || mb_strlen(trim(basename($originalName))) > 255) {
            throw new \InvalidArgumentException('Original file name must contain between 1 and 255 characters.');
        }

        if (mb_strlen(trim($defaultAlt)) > 500) {
            throw new \InvalidArgumentException('Default alternative text must not exceed 500 characters.');
        }

        $id = MediaAssetId::new();
        $processed = $this->processor->process($sourcePath, $id);

        try {
            $asset = MediaAsset::create(
                $id,
                $originalName,
                $processed->width,
                $processed->height,
                $processed->bytes,
                $defaultAlt,
                $processed->variants,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
            $this->assets->add($asset);
        } catch (\Throwable $exception) {
            $this->processor->remove($id);
            throw $exception;
        }

        return MediaAssetView::fromAsset($asset);
    }
}
