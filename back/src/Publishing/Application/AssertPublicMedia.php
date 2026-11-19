<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Media\Application\GetMediaAsset\GetMediaAsset;

final readonly class AssertPublicMedia
{
    public function __construct(private GetMediaAsset $getAsset)
    {
    }

    /** @param list<string> $assetIds */
    public function __invoke(array $assetIds): void
    {
        foreach (array_unique($assetIds) as $assetId) {
            if (($this->getAsset)($assetId)?->isPrivate === true) {
                throw new \InvalidArgumentException('Replace or remove private media before publishing.');
            }
        }
    }
}
