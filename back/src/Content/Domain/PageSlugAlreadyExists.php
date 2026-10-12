<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class PageSlugAlreadyExists extends \DomainException
{
    public function __construct(PageSlug $slug)
    {
        parent::__construct(sprintf('A page with slug "%s" already exists.', $slug->value));
    }
}
