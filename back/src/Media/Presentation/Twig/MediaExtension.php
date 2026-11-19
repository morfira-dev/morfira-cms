<?php

declare(strict_types=1);

namespace Cms\Media\Presentation\Twig;

use Cms\Media\Application\GetMediaAsset\GetMediaAsset;
use Cms\Media\Application\MediaAssetView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MediaExtension extends AbstractExtension
{
    public function __construct(
        private readonly GetMediaAsset $getMediaAsset,
        private readonly RequestStack $requests,
        private readonly AuthorizationCheckerInterface $authorization,
    ) {
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
            $asset = ($this->getMediaAsset)($id);
            if ($asset?->isPrivate === true
                && ($this->requests->getMainRequest()?->attributes->get('_route') !== 'content.pages.preview'
                    || !$this->authorization->isGranted('ROLE_EDITOR'))) {
                return null;
            }

            return $asset;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
