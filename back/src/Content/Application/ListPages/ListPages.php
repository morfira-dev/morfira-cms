<?php

declare(strict_types=1);

namespace Cms\Content\Application\ListPages;

use Cms\Content\Application\PageView;
use Cms\Content\Domain\PageRepository;

final readonly class ListPages
{
    public function __construct(private PageRepository $pages)
    {
    }

    /** @return list<PageView> */
    public function __invoke(): array
    {
        return array_map(PageView::fromPage(...), $this->pages->all());
    }
}
