<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Twig;

use Cms\Content\Application\RichTextSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class RichTextExtension extends AbstractExtension
{
    public function __construct(private readonly RichTextSanitizer $sanitizer)
    {
    }

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('cms_rich_text', $this->sanitize(...), ['is_safe' => ['html']]),
        ];
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
