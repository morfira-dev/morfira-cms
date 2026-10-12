<?php

declare(strict_types=1);

namespace Cms\Content\Application\UpdatePage;

use Cms\Content\Application\PageView;
use Cms\Content\Application\RichTextSanitizer;
use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageLayout;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Domain\PageSlug;
use Cms\Content\Domain\PageTitle;
use Cms\Theme\Application\ThemeCatalog;

final readonly class UpdatePage
{
    public function __construct(
        private PageRepository $pages,
        private RichTextSanitizer $richTextSanitizer,
        private ?ThemeCatalog $themes = null,
    ) {
    }

    /** @param list<array<string, mixed>> $blocks */
    public function __invoke(string $id, string $title, string $slug, array $blocks, PageSeo $seo, int $version, ?PageLayout $layout = null, ?string $locale = null, ?string $translationGroupId = null): PageView
    {
        $page = $this->pages->get(PageId::fromString($id));

        if ($page === null) {
            throw new PageNotFound();
        }

        if ($page->version !== $version) {
            throw new \Cms\Content\Domain\PageVersionConflict();
        }

        $resolvedLayout = $layout ?? $page->layout;
        if ($this->themes !== null && !$this->themes->supports($resolvedLayout->theme, $resolvedLayout->template)) {
            throw new \InvalidArgumentException('Unsupported theme or template.');
        }

        $revised = $page->revise(
            new PageTitle($title),
            new PageSlug($slug),
            array_map(PageBlock::fromArray(...), $this->sanitizeRichTextBlocks($blocks)),
            $seo,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            $resolvedLayout,
            $locale,
            $translationGroupId,
        );

        $this->pages->save($revised, $version);

        return PageView::fromPage($revised);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<array<string, mixed>>
     */
    private function sanitizeRichTextBlocks(array $blocks): array
    {
        return array_map(function (array $block): array {
            if (($block['type'] ?? null) !== 'richText' || !is_array($block['data'] ?? null)) {
                return $block;
            }

            $content = $block['data']['content'] ?? null;
            if (!is_string($content)) {
                return $block;
            }

            $block['data']['content'] = $this->richTextSanitizer->sanitize($content);

            return $block;
        }, $blocks);
    }
}
