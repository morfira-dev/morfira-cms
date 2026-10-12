<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class PageTranslationAlreadyExists extends \DomainException
{
    public function __construct(string $translationGroupId, string $locale)
    {
        parent::__construct(sprintf('Translation group "%s" already contains locale "%s".', $translationGroupId, $locale));
    }
}
