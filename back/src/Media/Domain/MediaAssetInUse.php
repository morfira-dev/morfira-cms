<?php

declare(strict_types=1);

namespace Cms\Media\Domain;

final class MediaAssetInUse extends \RuntimeException
{
    /** @param list<string> $usages */
    public function __construct(public readonly array $usages)
    {
        parent::__construct('Media asset is referenced by retained content and cannot be deleted.');
    }
}
