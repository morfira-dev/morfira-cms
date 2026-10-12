<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Html;

use Cms\Content\Application\RichTextSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final readonly class SymfonyRichTextSanitizer implements RichTextSanitizer
{
    private const int MAX_INPUT_BYTES = 100_000;

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('ol')
            ->allowElement('ul')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('a', ['href', 'title', 'target'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('svg')
            ->dropElement('math')
            ->dropElement('template')
            ->withMaxInputLength(self::MAX_INPUT_BYTES);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        if (strlen($html) > self::MAX_INPUT_BYTES) {
            throw new \InvalidArgumentException('Rich text input is too large to sanitize safely.');
        }

        return $this->sanitizer->sanitize($html);
    }
}
