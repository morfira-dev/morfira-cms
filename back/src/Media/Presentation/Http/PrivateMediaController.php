<?php

declare(strict_types=1);

namespace Cms\Media\Presentation\Http;

use Cms\Media\Application\ReadPrivateMediaVariant;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final readonly class PrivateMediaController
{
    private const array HEADERS = [
        'Cache-Control' => 'private, no-store',
        'X-Content-Type-Options' => 'nosniff',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];

    public function __construct(private ReadPrivateMediaVariant $read)
    {
    }

    #[Route('/api/v1/admin/media/{id}/variants/{filename}', name: 'media.assets.private_variant', methods: ['GET', 'HEAD'])]
    #[IsGranted('ROLE_EDITOR')]
    public function show(string $id, string $filename): Response
    {
        $path = ($this->read)($id, $filename);
        if ($path === null) {
            return new JsonResponse(['error' => 'Media variant not found.'], 404, self::HEADERS);
        }

        return new BinaryFileResponse(
            $path,
            headers: self::HEADERS + ['Content-Type' => 'image/webp'],
            public: false,
            autoLastModified: false,
        );
    }
}
