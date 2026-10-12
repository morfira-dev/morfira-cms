<?php

declare(strict_types=1);

namespace Cms\Media\Domain;

final class MediaAssetNotFound extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Media asset not found.');
    }
}
