<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

final class PublishedEntrySlugAlreadyExists extends \DomainException
{
    public function __construct(string $slug)
    {
        parent::__construct(sprintf('A published entry with slug "%s" already exists for this content type.', $slug));
    }
}
