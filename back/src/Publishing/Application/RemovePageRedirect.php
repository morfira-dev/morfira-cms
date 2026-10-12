<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageSlug;
use Cms\Publishing\Domain\PublishedPageRepository;

final readonly class RemovePageRedirect
{
    public function __construct(private PageRepository $pages,private PublishedPageRepository $published){}
    public function __invoke(string $pageId,string $sourceSlug):void
    {
        $id=PageId::fromString($pageId);if($this->pages->get($id)===null)throw new PageNotFound();
        $slug = new PageSlug($sourceSlug);
        $this->published->removeRedirect($id->value,$slug->value);
    }
}
