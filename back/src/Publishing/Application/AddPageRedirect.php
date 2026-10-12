<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;
use Cms\Content\Domain\PageSlug;
use Cms\Publishing\Domain\PublishedPageRepository;

final readonly class AddPageRedirect
{
    public function __construct(private PageRepository $pages, private PublishedPageRepository $published) {}
    /** @return array{sourceSlug:string,targetSlug:string} */
    public function __invoke(string $pageId,string $sourceSlug):array
    {
        $id=PageId::fromString($pageId);if($this->pages->get($id)===null)throw new PageNotFound();
        $slug = new PageSlug($sourceSlug);
        return $this->published->addRedirect($id->value,$slug->value)->toArray();
    }
}
