<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

final class ContentTypeKeyAlreadyExists extends \DomainException
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf('A content type with key "%s" already exists.', $key));
    }
}
