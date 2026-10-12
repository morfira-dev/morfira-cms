<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class EntrySlugAlreadyExists extends \DomainException
{
    public function __construct(string $slug)
    {
        parent::__construct(sprintf('An entry with slug "%s" already exists for this content type.', $slug));
    }
}
