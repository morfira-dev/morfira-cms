<?php

declare(strict_types=1);

namespace Cms\Media\Application;

final class MediaTooLarge extends \InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Image must be between 1 byte and 10 MB.');
    }
}
