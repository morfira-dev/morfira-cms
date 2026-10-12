<?php

declare(strict_types=1);

namespace Cms\Media\Application;

final class UnsupportedMediaType extends \InvalidArgumentException
{
    public function __construct(string $message = 'Only JPEG, PNG and WebP images are supported.')
    {
        parent::__construct($message);
    }
}
