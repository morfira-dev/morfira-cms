<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use Cms\Content\Application\RichTextSanitizer;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * @phpstan-type ImageReference array{assetId:string,alt:string}
 * @phpstan-type Segment array{type:'richText',html:string}|array{type:'image',assetId:string,alt:string}
 * @phpstan-type Wrapper array{open:string,close:string}
 */
final readonly class SegmentWordPressImages
{
    private const int MAX_HTML_BYTES = 100_000;
    private const array DISCARD = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript'];
    private const array WRAPPERS = ['p', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'ol', 'ul', 'li', 'blockquote', 'code', 'pre', 'a'];

    public function __construct(private RichTextSanitizer $sanitizer)
    {
    }

    /**
     * Resolve only previously identified WordPress attachments, without requesting the source URL.
     *
     * @param \Closure(string, ?string): ?ImageReference $resolve
     * @return array{segments:list<Segment>,missingImages:int}
     */
    public function __invoke(string $html, \Closure $resolve): array
    {
        if (strlen($html) > self::MAX_HTML_BYTES) {
            throw new \InvalidArgumentException('Rich text input is too large to sanitize safely.');
        }

        $segments = [];
        $missing = 0;
        if (stripos($html, '<img') === false) {
            $this->appendRichText($segments, $html);

            return ['segments' => $segments, 'missingImages' => $missing];
        }

        $document = new DOMDocument();
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            $this->appendRichText($segments, $html);

            return ['segments' => $segments, 'missingImages' => $missing];
        }

        $buffer = '';
        foreach ($this->walk($body, []) as $chunk) {
            if (is_string($chunk)) {
                $buffer .= $chunk;
                continue;
            }

            $image = $this->resolveImage($chunk['image'], $resolve);
            if ($image === null) {
                ++$missing;
                continue;
            }

            $wrappers = $chunk['wrappers'];
            $this->appendRichText($segments, $buffer.implode('', array_reverse(array_column($wrappers, 'close'))));
            $segments[] = $image;
            $buffer = implode('', array_column($wrappers, 'open'));
        }
        $this->appendRichText($segments, $buffer);

        return ['segments' => $segments, 'missingImages' => $missing];
    }

    /**
     * @param list<Wrapper> $wrappers
     * @return \Generator<int,string|array{image:DOMElement,wrappers:list<Wrapper>}>
     */
    private function walk(DOMNode $parent, array $wrappers): \Generator
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMText) {
                yield self::escape($node->data);
                continue;
            }
            if (!$node instanceof DOMElement || in_array($node->tagName, self::DISCARD, true)) {
                continue;
            }
            if ($node->tagName === 'img') {
                yield ['image' => $node, 'wrappers' => $wrappers];
                continue;
            }
            if ($node->tagName === 'br') {
                yield '<br>';
                continue;
            }
            $tag = $node->tagName === 'figcaption' ? 'p' : $node->tagName;
            if (!in_array($tag, self::WRAPPERS, true)) {
                yield from $this->walk($node, $wrappers);
                continue;
            }

            $wrapper = ['open' => $this->openTag($node, $tag), 'close' => '</'.$tag.'>'];
            yield $wrapper['open'];
            yield from $this->walk($node, [...$wrappers, $wrapper]);
            yield $wrapper['close'];
        }
    }

    private function openTag(DOMElement $element, string $tag): string
    {
        $attributes = '';
        if ($tag === 'a') {
            foreach (['href', 'title', 'target'] as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    $attributes .= ' '.$attribute.'="'.self::escape($element->getAttribute($attribute)).'"';
                }
            }
        }

        return '<'.$tag.$attributes.'>';
    }

    /**
     * @param \Closure(string, ?string): ?ImageReference $resolve
     * @return array{type:'image',assetId:string,alt:string}|null
     */
    private function resolveImage(DOMElement $image, \Closure $resolve): ?array
    {
        $wordpressId = preg_match('/(?:^|\s)wp-image-([1-9][0-9]{0,19})(?:\s|$)/', $image->getAttribute('class'), $matches) === 1 ? $matches[1] : null;
        $sources = array_unique([
            trim($image->getAttribute('src')),
            trim($image->getAttribute('data-src')),
            trim($image->getAttribute('data-lazy-src')),
        ]);
        foreach ($sources as $source) {
            if ($source === '' && $wordpressId === null) {
                continue;
            }
            $asset = $resolve($source, $wordpressId);
            if ($asset === null) {
                continue;
            }

            $alt = trim($image->getAttribute('alt'));
            if ($alt === '') {
                $alt = trim($asset['alt']);
            }

            return ['type' => 'image', 'assetId' => $asset['assetId'], 'alt' => $alt === '' ? 'Image' : mb_substr($alt, 0, 300)];
        }

        return null;
    }

    /** @param list<Segment> $segments */
    private function appendRichText(array &$segments, string $html): void
    {
        $safe = trim($this->sanitizer->sanitize($html));
        $visibleText = html_entity_decode(strip_tags($safe), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/\A[\s\x{00A0}]*\z/u', $visibleText) !== 1) {
            $segments[] = ['type' => 'richText', 'html' => $safe];
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
