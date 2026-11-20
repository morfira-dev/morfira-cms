<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use DOMDocument;
use DOMElement;
use DOMText;

final class NormalizeWordPressStaticBlock
{
    private const array HEADING_CLASSES = [
        'wp-block-site-title', 'wp-block-post-title', 'wp-block-query-title',
        'wp-block-comments-title', 'wp-block-post-comments-title',
    ];
    private const array ARCHIVE_CLASSES = [
        'wp-block-archives', 'wp-block-archives-dropdown',
        'wp-block-categories', 'wp-block-categories-dropdown',
    ];
    private const array INLINE_TAGS = ['a', 'b', 'code', 'em', 'i', 's', 'span', 'strong', 'u'];

    public static function normalize(DOMElement $block): void
    {
        $document = $block->ownerDocument;
        if ($document === null || $block->parentNode === null) {
            return;
        }

        $classes = preg_split('/\s+/', $block->getAttribute('class')) ?: [];
        $heading = match ($block->tagName) {
            'h1' => 'h2',
            'h5', 'h6' => 'h4',
            default => null,
        };
        if ($heading !== null && array_intersect($classes, self::HEADING_CLASSES) !== []) {
            self::replaceTag($document, $block, $heading);
        } elseif ($block->tagName === 'pre' && array_intersect($classes, ['wp-block-code', 'wp-block-preformatted']) !== []) {
            if (preg_match('/\t| {2,}|(?:\A|[\r\n]) | (?:[\r\n]|\z)/', $block->textContent) === 0) {
                self::normalizeBreaks($document, self::replaceTag($document, $block, 'p'));
            }
        } elseif (array_intersect($classes, self::ARCHIVE_CLASSES) !== []) {
            if ($block->tagName === 'select') {
                self::normalizeSelect($document, $block);
            } else {
                foreach (iterator_to_array($block->childNodes) as $child) {
                    if ($child instanceof DOMElement && $child->tagName === 'select') {
                        self::normalizeSelect($document, $child);
                    }
                }
            }
        }
    }

    private static function replaceTag(DOMDocument $document, DOMElement $element, string $tag): DOMElement
    {
        $replacement = $document->createElement($tag);
        while ($element->firstChild !== null) {
            $replacement->appendChild($element->firstChild);
        }
        $element->replaceWith($replacement);

        return $replacement;
    }

    private static function normalizeBreaks(DOMDocument $document, DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement && in_array($child->tagName, self::INLINE_TAGS, true)) {
                self::normalizeBreaks($document, $child);
            } elseif ($child instanceof DOMText) {
                $lines = preg_split('/\r\n|\r|\n/', $child->data);
                if ($lines === false || count($lines) < 2) {
                    continue;
                }
                $child->data = $lines[0];
                $next = $child->nextSibling;
                foreach (array_slice($lines, 1) as $line) {
                    $element->insertBefore($document->createElement('br'), $next);
                    $element->insertBefore($document->createTextNode($line), $next);
                }
            }
        }
    }

    private static function normalizeSelect(DOMDocument $document, DOMElement $select): void
    {
        if ($select->hasAttribute('disabled') || $select->hasAttribute('hidden')) {
            return;
        }

        $list = $document->createElement('ul');
        foreach ($select->getElementsByTagName('option') as $option) {
            $parent = $option->parentNode;
            if ($parent !== $select && (!$parent instanceof DOMElement || $parent->tagName !== 'optgroup'
                || $parent->parentNode !== $select || $parent->hasAttribute('disabled') || $parent->hasAttribute('hidden'))) {
                continue;
            }
            $url = trim($option->getAttribute('value'));
            if ($option->hasAttribute('disabled') || $option->hasAttribute('hidden') || !self::isExplicitUrl($url)) {
                continue;
            }

            $label = self::optionLabel($option);
            if ($label === '') {
                continue;
            }
            $item = $document->createElement('li');
            $anchor = $document->createElement('a');
            $anchor->setAttribute('href', $url);
            $anchor->appendChild($document->createTextNode($label));
            $item->appendChild($anchor);
            $list->appendChild($item);
        }

        if ($list->hasChildNodes()) {
            $select->replaceWith($list);
        }
    }

    private static function optionLabel(DOMElement $option): string
    {
        $label = '';
        foreach ($option->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return '';
            }
            if ($child instanceof DOMText) {
                $label .= $child->data;
            }
        }

        $explicitLabel = trim($option->getAttribute('label'));

        return $explicitLabel === '' ? trim($label) : $explicitLabel;
    }

    private static function isExplicitUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (isset($parts['scheme'])) {
            return in_array(strtolower($parts['scheme']), ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        $path = rawurldecode($parts['path'] ?? '');

        return !isset($parts['host']) && str_starts_with($url, '/') && !str_starts_with($path, '//')
            && preg_match('/[\x00-\x1f\x7f\\\\]/', $path) === 0;
    }
}
