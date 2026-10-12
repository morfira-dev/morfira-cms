<?php

declare(strict_types=1);

namespace Cms\Media\Infrastructure\Image;

use Cms\Media\Application\ImageProcessor;
use Cms\Media\Application\ProcessedImage;
use Cms\Media\Application\UnsupportedMediaType;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaVariant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class LocalGdImageProcessor implements ImageProcessor
{
    private const MAX_PIXELS = 40_000_000;

    /** @var list<int> */
    private const RESPONSIVE_WIDTHS = [480, 960, 1600, 2000];

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/media')]
        private string $publicDirectory,
    ) {
    }

    public function process(string $sourcePath, MediaAssetId $assetId): ProcessedImage
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('The GD extension is required to process images.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
        if (!is_string($mimeType) || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new UnsupportedMediaType();
        }

        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('The uploaded file is not a valid image.');
        }

        [$sourceWidth, $sourceHeight, $imageType] = $imageInfo;
        $detectedMimeType = image_type_to_mime_type($imageType);
        if ($detectedMimeType !== $mimeType || !in_array($detectedMimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new UnsupportedMediaType('The uploaded image type does not match its contents.');
        }

        if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceWidth * $sourceHeight > self::MAX_PIXELS) {
            throw new \InvalidArgumentException('Image dimensions are invalid or exceed 40 megapixels.');
        }

        $source = $this->decode($sourcePath, $imageType);
        if (!$source instanceof \GdImage) {
            throw new \InvalidArgumentException('The uploaded image could not be decoded safely.');
        }

        $directory = $this->assetDirectory($assetId);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Media storage directory could not be created.');
        }

        $widths = array_values(array_filter(
            self::RESPONSIVE_WIDTHS,
            static fn (int $width): bool => $width < $sourceWidth,
        ));
        $widths[] = min($sourceWidth, 2000);
        $widths = array_values(array_unique($widths));
        sort($widths);

        $variants = [];
        $totalBytes = 0;

        try {
            foreach ($widths as $width) {
                $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
                $destination = imagecreatetruecolor($width, $height);
                if (!$destination instanceof \GdImage) {
                    throw new \RuntimeException('A responsive image canvas could not be created.');
                }

                imagealphablending($destination, false);
                imagesavealpha($destination, true);
                $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
                if ($transparent === false) {
                    throw new \RuntimeException('A transparent image color could not be allocated.');
                }
                imagefilledrectangle($destination, 0, 0, $width, $height, $transparent);

                imagecopyresampled($destination, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

                $filename = sprintf('%dw.webp', $width);
                $targetPath = $directory . '/' . $filename;
                $temporaryPath = $directory . '/.' . $filename . '.' . bin2hex(random_bytes(6)) . '.tmp';

                try {
                    if (!imagewebp($destination, $temporaryPath, 82)) {
                        throw new \RuntimeException('The image could not be encoded as WebP.');
                    }

                    $bytes = filesize($temporaryPath);
                    if (!is_int($bytes) || $bytes < 1 || !rename($temporaryPath, $targetPath)) {
                        throw new \RuntimeException('The encoded image could not be stored.');
                    }
                } finally {
                    if (is_file($temporaryPath)) {
                        unlink($temporaryPath);
                    }
                }

                $totalBytes += $bytes;
                $variants[] = new MediaVariant(
                    $width,
                    $height,
                    $bytes,
                    sprintf('/media/%s/%s', $assetId->value, $filename),
                );
            }
        } catch (\Throwable $exception) {
            $this->remove($assetId);
            throw $exception;
        }

        /** @var non-empty-list<MediaVariant> $variants */
        $largest = $variants[array_key_last($variants)];

        return new ProcessedImage($largest->width, $largest->height, $totalBytes, $variants);
    }

    public function remove(MediaAssetId $assetId): void
    {
        $directory = $this->assetDirectory($assetId);
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        $hiddenFiles = glob($directory . '/.*');
        if (is_array($hiddenFiles)) {
            foreach ($hiddenFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        rmdir($directory);
    }

    private function assetDirectory(MediaAssetId $assetId): string
    {
        return rtrim($this->publicDirectory, '/') . '/' . $assetId->value;
    }

    private function decode(string $sourcePath, int $imageType): \GdImage|false
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => false,
        };
    }
}
