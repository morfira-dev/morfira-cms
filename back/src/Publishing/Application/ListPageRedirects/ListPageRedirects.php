<?php

declare(strict_types=1);

namespace Cms\Publishing\Application\ListPageRedirects;

use Cms\Publishing\Domain\PublishedPageRepository;
use Cms\Publishing\Domain\PublishedRedirect;

final readonly class ListPageRedirects
{
    public function __construct(private PublishedPageRepository $pages)
    {
    }

    /** @return list<array{sourceSlug: string, targetSlug: string}> */
    public function __invoke(string $pageId): array
    {
        return array_map(
            static fn (PublishedRedirect $redirect): array => $redirect->toArray(),
            $this->pages->redirectsForPage($pageId),
        );
    }
}
