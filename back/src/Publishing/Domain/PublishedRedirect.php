<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

final readonly class PublishedRedirect
{
    public function __construct(
        public string $sourceSlug,
        public string $targetSlug,
    ) {
        foreach ([$sourceSlug, $targetSlug] as $slug) {
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 191) {
                throw new \InvalidArgumentException('Published redirect slugs are invalid.');
            }
        }

        if ($sourceSlug === $targetSlug) {
            throw new \InvalidArgumentException('A published redirect cannot point to itself.');
        }
    }

    /** @return array{sourceSlug: string, targetSlug: string} */
    public function toArray(): array
    {
        return ['sourceSlug' => $this->sourceSlug, 'targetSlug' => $this->targetSlug];
    }
}
