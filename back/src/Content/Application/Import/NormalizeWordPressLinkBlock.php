<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use DOMElement;
use DOMNode;
use DOMText;

final class NormalizeWordPressLinkBlock
{
    private const array NAVIGATION_CLASSES = ['wp-block-navigation', 'wp-block-navigation-link', 'wp-block-navigation-submenu'];
    private const array LINK_CLASSES = [
        ...self::NAVIGATION_CLASSES,
        'wp-block-social-links', 'wp-block-social-link', 'wp-block-page-list', 'wp-block-rss',
        'wp-block-read-more', 'wp-block-post-excerpt__more-link', 'wp-block-home-link', 'wp-block-loginout',
    ];
    private const array IGNORED_SUBTREES = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript',
        'button', 'input', 'select', 'textarea',
    ];

    public static function normalize(DOMElement $block): void
    {
        if ($block->tagName === 'button' && self::hasContext($block, self::NAVIGATION_CLASSES)) {
            $block->remove();

            return;
        }
        if ($block->tagName !== 'a' || trim($block->getAttribute('href')) === '' || !self::hasContext($block, self::LINK_CLASSES)) {
            return;
        }

        $imageAlt = '';
        foreach (self::projectedNodes($block) as $node) {
            if ($node instanceof DOMText && self::label($node->data) !== '') {
                return;
            }
            if ($imageAlt === '' && $node instanceof DOMElement && $node->tagName === 'img') {
                $imageAlt = self::label($node->getAttribute('alt'));
            }
        }

        $label = self::label($block->getAttribute('aria-label'));
        if ($label === '') {
            $label = self::label($block->getAttribute('title'));
        }
        if ($label === '') {
            $label = $imageAlt;
        }
        $document = $block->ownerDocument;
        if ($label !== '' && $document !== null) {
            $block->replaceChildren($document->createTextNode($label));
        }
    }

    /** @param list<string> $classes */
    private static function hasContext(DOMElement $element, array $classes): bool
    {
        $supported = false;
        for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node !== $element && in_array($node->tagName, self::IGNORED_SUBTREES, true)) {
                return false;
            }
            if (!$supported) {
                $tokens = preg_split('/\s+/', $node->getAttribute('class')) ?: [];
                $supported = array_intersect($classes, $tokens) !== [];
            }
        }

        return $supported;
    }

    /** @return \Generator<int,DOMNode> */
    private static function projectedNodes(DOMElement $element): \Generator
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && in_array($child->tagName, self::IGNORED_SUBTREES, true)) {
                continue;
            }
            yield $child;
            if ($child instanceof DOMElement) {
                yield from self::projectedNodes($child);
            }
        }
    }

    private static function label(string $value): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? '');
    }
}
