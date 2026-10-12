<?php

declare(strict_types=1);

namespace Cms\Content\Application;

interface RichTextSanitizer
{
    public function sanitize(string $html): string;
}
