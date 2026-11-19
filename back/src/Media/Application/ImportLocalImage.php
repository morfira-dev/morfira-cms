<?php

declare(strict_types=1);

namespace Cms\Media\Application;

use Cms\Media\Application\UploadImage\UploadImage;
use Cms\Media\Domain\MediaAssetId;

final readonly class ImportLocalImage
{
    public function __construct(private UploadImage $upload, private ImageProcessor $processor)
    {
    }

    public function __invoke(string $directory, string $relativePath, string $alt, bool $apply): ?MediaAssetView
    {
        $source = $this->sourcePath($directory, $relativePath);
        $name = trim(basename($relativePath));
        if ($name === '' || mb_strlen($name) > 255 || mb_strlen(trim($alt)) > 500) {
            throw new \InvalidArgumentException('The attachment file name or alternative text exceeds the media limits.');
        }

        $bytes = $this->read($source);
        $image = @getimagesizefromstring($bytes);
        if ($image === false || !in_array($image[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new UnsupportedMediaType();
        }
        if ($image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > 40_000_000) {
            throw new \InvalidArgumentException('Image dimensions are invalid or exceed 40 megapixels.');
        }

        if (!$apply) {
            return null;
        }
        $temporary = tmpfile();
        if ($temporary === false) {
            throw new \RuntimeException('Could not stage the import image.');
        }

        try {
            if (fwrite($temporary, $bytes) !== strlen($bytes) || !fflush($temporary)) {
                throw new \RuntimeException('Could not stage the import image.');
            }

            $temporaryPath = stream_get_meta_data($temporary)['uri'] ?? null;
            if (!is_string($temporaryPath)) {
                throw new \RuntimeException('Could not resolve the staged import image.');
            }

            return ($this->upload)($temporaryPath, $name, $alt);
        } finally {
            fclose($temporary);
        }
    }

    /** Discard only the variants created by a failed import transaction. */
    public function rollback(MediaAssetView $asset): void
    {
        $this->processor->remove(MediaAssetId::fromString($asset->id));
    }

    private function sourcePath(string $directory, string $relativePath): string
    {
        if (str_contains($directory, "\0") || str_contains($directory, '://')) {
            throw new \InvalidArgumentException('Provide a local uploads directory.');
        }
        clearstatcache(true);
        $root = realpath($directory);
        if ($root === false || $root === DIRECTORY_SEPARATOR || !is_dir($root)) {
            throw new \InvalidArgumentException('Provide a local uploads directory.');
        }
        if (strlen($relativePath) > 4096 || preg_match('/[\x00-\x1f\x7f\\\\:]/', $relativePath) === 1
            || array_intersect(explode('/', $relativePath), ['', '.', '..']) !== []) {
            throw new \InvalidArgumentException('The attachment path must be relative to the uploads directory.');
        }

        $prefix = $root . DIRECTORY_SEPARATOR;
        $source = realpath($prefix . $relativePath);
        if ($source === false || !str_starts_with($source, $prefix) || !is_file($source)) {
            throw new \InvalidArgumentException('The attachment is unavailable inside the uploads directory.');
        }

        return $source;
    }

    private function read(string $source): string
    {
        $file = @fopen($source, 'rb');
        if ($file === false) {
            throw new \InvalidArgumentException('The attachment is not readable.');
        }

        try {
            $stat = fstat($file);
            clearstatcache(true, $source);
            $expected = @lstat($source);
            if ($stat === false || $expected === false || ($stat['mode'] & 0170000) !== 0100000
                || $stat['dev'] !== $expected['dev'] || $stat['ino'] !== $expected['ino'] || realpath($source) !== $source) {
                throw new \InvalidArgumentException('The attachment is unavailable inside the uploads directory.');
            }
            if ($stat['size'] < 1 || $stat['size'] > UploadImage::MAX_BYTES) {
                throw new MediaTooLarge();
            }
            $bytes = stream_get_contents($file, UploadImage::MAX_BYTES + 1);
            if ($bytes === false || strlen($bytes) !== $stat['size']) {
                throw new \InvalidArgumentException('The attachment changed or could not be read.');
            }

            return $bytes;
        } finally {
            fclose($file);
        }
    }
}
