<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\ImportedRedirectRepository;
use Cms\Publishing\Domain\ImportedRedirectSource;
use Cms\Publishing\Domain\PublicationTarget;

final readonly class RegisterImportedRedirect
{
    public function __construct(private ImportedRedirectRepository $redirects)
    {
    }

    /** @return 'planned'|'registered'|'already_registered'|'conflict'|'unsupported' */
    public function __invoke(string $sourceUrl, string $targetType, ?string $targetId): string
    {
        $source = ImportedRedirectSource::fromUrl($sourceUrl);
        $type = PublicationTarget::tryFrom($targetType);
        if ($source === null || $type === null || ($targetId !== null && preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/', $targetId) !== 1)) {
            return 'unsupported';
        }

        $owner = $this->redirects->owner($source);
        if ($owner !== null) {
            return $owner['type'] === $type && $owner['id'] === $targetId ? 'already_registered' : 'conflict';
        }
        if ($targetId === null) {
            return 'planned';
        }

        return $this->redirects->register($source, $type, $targetId) ? 'registered' : 'conflict';
    }
}
