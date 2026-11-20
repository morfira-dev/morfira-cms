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
            ->allowElement('hr')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('h5')
            ->allowElement('h6')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('sub')
            ->allowElement('sup')
            ->allowElement('span', ['data-mw-node', 'data-mention-id'])
            ->allowElement('ol', ['start', 'reversed'])
            ->allowElement('ul')
            ->allowElement('li', ['data-checklist-checked'])
            ->allowElement('blockquote')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            ->allowElement('table', ['width', 'height'])
            ->allowElement('caption')
            ->allowElement('colgroup')
            ->allowElement('col', ['span', 'width', 'height'])
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tfoot')
            ->allowElement('tr')
            ->allowElement('th', ['colspan', 'rowspan', 'scope'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowAttribute('style', ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'span', 'ul', 'ol', 'li', 'img', 'table', 'col', 'td', 'th'])
            ->allowAttribute('dir', ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'ul', 'ol', 'li', 'td', 'th'])
            ->allowElement('a', ['href', 'title', 'target'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['https'])
            ->allowRelativeMedias()
            ->withAttributeSanitizer(new RichTextAttributeSanitizer())
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

        $sanitized = $this->sanitizer->sanitize($html);

        // The sanitizer serializes void elements with a fixed shape. Omit image placeholders whose source was rejected.
        return preg_replace_callback('~<img(?: [a-z-]+(?:="[^"]*")?)* />~', static fn (array $image): string => str_contains($image[0], ' src="') ? $image[0] : '', $sanitized) ?? $sanitized;
    }
}
