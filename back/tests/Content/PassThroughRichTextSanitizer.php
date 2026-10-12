<?php

declare(strict_types=1);

namespace Cms\Tests\Content;

use Cms\Content\Application\RichTextSanitizer;

final readonly class PassThroughRichTextSanitizer implements RichTextSanitizer
{
    public function sanitize(string $html): string
    {
        return $html;
    }
}
