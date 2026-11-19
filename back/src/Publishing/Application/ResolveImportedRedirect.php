<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\ImportedRedirectRepository;
use Cms\Publishing\Domain\ImportedRedirectSource;

final readonly class ResolveImportedRedirect
{
    public function __construct(private ImportedRedirectRepository $redirects)
    {
    }

    public function __invoke(string $path, ?string $query): ?string
    {
        $source = ImportedRedirectSource::fromRequest($path, $query);
        if ($source === null) {
            return null;
        }

        $target = $this->redirects->activeTargetPath($source);
        if (($target === $source && $path === $source) || $target === '/404') {
            return null;
        }

        return $target;
    }
}
