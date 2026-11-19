<?php

declare(strict_types=1);

namespace Cms\Media\Infrastructure\Image;

use Cms\Media\Application\PrivateImageProcessor;
use Cms\Media\Application\ProcessedImage;
use Cms\Media\Domain\MediaAssetId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class LocalPrivateImageProcessor implements PrivateImageProcessor
{
    private LocalGdImageProcessor $processor;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/private-media')]
        private string $directory,
    ) {
        $this->processor = new LocalGdImageProcessor($directory, true);
    }

    public function process(string $sourcePath, MediaAssetId $assetId): ProcessedImage
    {
        return $this->processor->process($sourcePath, $assetId);
    }

    public function remove(MediaAssetId $assetId): void
    {
        $this->processor->remove($assetId);
    }

    public function variantPath(MediaAssetId $id, string $filename): ?string
    {
        if (preg_match('/\A[1-9][0-9]{0,3}w\.webp\z/', $filename) !== 1) {
            return null;
        }

        $root = realpath($this->directory);
        if ($root === false) {
            return null;
        }
        $path = $root.'/'.$id->value.'/'.$filename;

        return realpath($path) === $path && is_file($path) && is_readable($path) ? $path : null;
    }
}
