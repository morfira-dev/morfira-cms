<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

final class PublishedSlugAlreadyExists extends \DomainException
{
    public function __construct(string $slug)
    {
        parent::__construct(sprintf('The public slug "%s" is already used by another page.', $slug));
    }
}
