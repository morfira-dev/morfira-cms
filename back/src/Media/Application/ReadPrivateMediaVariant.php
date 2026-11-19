<?php

declare(strict_types=1);

namespace Cms\Media\Application;

use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaAssetRepository;
use Cms\Media\Domain\MediaVariant;

final readonly class ReadPrivateMediaVariant
{
    public function __construct(private MediaAssetRepository $assets, private PrivateImageProcessor $images)
    {
    }

    public function __invoke(string $id, string $filename): ?string
    {
        if (preg_match('/\A([1-9][0-9]{0,3})w\.webp\z/', $filename, $matches) !== 1) {
            return null;
        }
        try {
            $assetId = MediaAssetId::fromString($id);
        } catch (\InvalidArgumentException) {
            return null;
        }
        $asset = $this->assets->get($assetId);
        if ($asset === null || !$asset->isPrivate) {
            return null;
        }

        $url = MediaVariant::urlFor($assetId, (int) $matches[1], true);
        foreach ($asset->variants as $variant) {
            if ($variant->url === $url) {
                return $this->images->variantPath($assetId, $filename);
            }
        }

        return null;
    }
}
