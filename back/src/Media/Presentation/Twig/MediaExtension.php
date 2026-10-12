<?php

declare(strict_types=1);

namespace Cms\Media\Presentation\Twig;

use Cms\Media\Application\GetMediaAsset\GetMediaAsset;
use Cms\Media\Application\MediaAssetView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MediaExtension extends AbstractExtension
{
    public function __construct(private readonly GetMediaAsset $getMediaAsset)
    {
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('media_asset', $this->find(...)),
        ];
    }

    public function find(string $id): ?MediaAssetView
    {
        try {
            return ($this->getMediaAsset)($id);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
