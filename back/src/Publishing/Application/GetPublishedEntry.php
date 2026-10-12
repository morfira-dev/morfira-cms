<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Domain\PublishedEntryRepository;

final readonly class GetPublishedEntry
{
    public function __construct(private PublishedEntryRepository $entries)
    {
    }

    public function __invoke(string $type, string $slug): ?PublishedEntryView
    {
        $entry = $this->entries->find($type, $slug);

        return null === $entry ? null : PublishedEntryView::fromEntry($entry);
    }
}
