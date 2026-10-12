<?php

declare(strict_types=1);

namespace Cms\Publishing\Application\PublishPage;

use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageVersionConflict;
use Cms\Publishing\Application\PublishedPageView;
use Cms\Publishing\Domain\PublishedPage;
use Cms\Publishing\Domain\PublishedPageRepository;

final readonly class PublishPage
{
    public function __construct(
        private PageRepository $drafts,
        private PublishedPageRepository $publishedPages,
    ) {
    }

    public function __invoke(string $pageId, int $expectedVersion): PublishedPageView
    {
        $draft = $this->drafts->get(PageId::fromString($pageId));

        if ($draft === null) {
            throw new PageNotFound();
        }

        if ($draft->version !== $expectedVersion) {
            throw new PageVersionConflict();
        }

        $snapshot = new PublishedPage(
            self::newUuid(),
            $draft->id->value,
            $draft->version,
            $draft->title->value,
            $draft->slug->value,
            array_map(static fn (PageBlock $block): array => $block->toArray(), $draft->blocks),
            $draft->seo->toArray(),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        return PublishedPageView::fromPublishedPage($this->publishedPages->publish($snapshot));
    }

    private static function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
