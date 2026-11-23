<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class NormalizeWordPressTextBlock
{
    public static function normalize(DOMElement $block): void
    {
        $document = $block->ownerDocument;
        if ($document === null || $block->parentNode === null) {
            return;
        }

        $classes = preg_split('/\s+/', $block->getAttribute('class')) ?: [];
        if (in_array('wp-block-heading', $classes, true)) {
            $level = match ($block->tagName) {
                'h1' => 'h2',
                'h5', 'h6' => 'h4',
                default => null,
            };
            if ($level !== null) {
                $heading = self::replaceTag($document, $block, $level);
                if ($block->hasAttribute('id')) {
                    $heading->setAttribute('id', $block->getAttribute('id'));
                }
            }
        } elseif ($block->tagName === 'div' && in_array('wp-block-button', $classes, true)) {
            $children = self::significantChildren($block);
            if (count($children) === 1 && $children[0] instanceof DOMElement && $children[0]->tagName === 'a') {
                self::replaceTag($document, $block, 'p');
            }
        } elseif ($block->tagName === 'blockquote' && in_array('wp-block-quote', $classes, true)) {
            self::normalizeCitations($document, $block);
        } elseif ($block->tagName === 'figure' && in_array('wp-block-pullquote', $classes, true)) {
            $quote = self::directElement($block, 'blockquote');
            if ($quote !== null) {
                self::normalizeCitations($document, $quote);
            }
            self::normalizeCitations($document, $block);
        } elseif ($block->tagName === 'details' && in_array('wp-block-details', $classes, true)) {
            $summary = self::directElement($block, 'summary');
            if ($summary !== null) {
                self::replaceTag($document, $summary, 'p', 'strong');
            }
        } elseif ($block->tagName === 'pre' && in_array('wp-block-verse', $classes, true)) {
            $verse = self::replaceTag($document, $block, 'p');
            self::normalizeVerseBreaks($document, $verse);
        } elseif (in_array('wp-block-table', $classes, true)) {
            $table = $block->tagName === 'table' ? $block : self::directElement($block, 'table');
            if ($table !== null) {
                self::normalizeTable($document, $table);
            }
        }
    }

    private static function normalizeCitations(DOMDocument $document, DOMElement $quote): void
    {
        foreach (self::significantChildren($quote) as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'cite') {
                self::replaceTag($document, $child, 'p', 'em');
            }
        }
    }

    private static function normalizeVerseBreaks(DOMDocument $document, DOMElement $verse): void
    {
        foreach (iterator_to_array($verse->childNodes) as $child) {
            if ($child instanceof DOMElement && in_array($child->tagName, ['strong', 'b', 'em', 'i', 'u', 's', 'a', 'code', 'span'], true)) {
                self::normalizeVerseBreaks($document, $child);
            } elseif ($child instanceof DOMText && str_contains($child->data, "\n")) {
                $lines = explode("\n", $child->data);
                foreach ($lines as $index => $line) {
                    if ($index > 0) {
                        $verse->insertBefore($document->createElement('br'), $child);
                    }
                    $verse->insertBefore($document->createTextNode($line), $child);
                }
                $child->remove();
            }
        }
    }

    private static function normalizeTable(DOMDocument $document, DOMElement $table): void
    {
        $rows = [];
        $caption = null;
        foreach (self::significantChildren($table) as $child) {
            if (!$child instanceof DOMElement) {
                return;
            }
            if ($child->tagName === 'caption' && $caption === null && $rows === []) {
                $caption = $child;
            } elseif ($child->tagName === 'tr') {
                $rows[] = $child;
            } elseif (in_array($child->tagName, ['thead', 'tbody', 'tfoot'], true)) {
                array_push($rows, ...self::significantChildren($child));
            } elseif ($child->tagName !== 'colgroup') {
                return;
            }
        }
        if ($rows === []) {
            return;
        }

        $rowCells = [];
        foreach ($rows as $row) {
            if (!$row instanceof DOMElement || $row->tagName !== 'tr') {
                return;
            }
            $cells = [];
            foreach (self::significantChildren($row) as $cell) {
                if (!$cell instanceof DOMElement || !in_array($cell->tagName, ['th', 'td'], true)) {
                    return;
                }
                $cells[] = $cell;
            }
            $rowCells[] = $cells;
        }

        $list = $document->createElement('ul');
        foreach ($rowCells as $cells) {
            $item = $document->createElement('li');
            foreach ($cells as $index => $cell) {
                if ($index > 0) {
                    $item->appendChild($document->createTextNode(' | '));
                }
                self::moveChildren($cell, $item);
            }
            $list->appendChild($item);
        }
        if ($caption !== null) {
            $paragraph = $document->createElement('p');
            self::moveChildren($caption, $paragraph);
            $table->before($paragraph);
        }
        $table->replaceWith($list);
    }

    private static function replaceTag(DOMDocument $document, DOMElement $element, string $tag, ?string $wrapper = null): DOMElement
    {
        $replacement = $document->createElement($tag);
        $container = $replacement;
        if ($wrapper !== null) {
            $container = $document->createElement($wrapper);
            $replacement->appendChild($container);
        }
        self::moveChildren($element, $container);
        $element->replaceWith($replacement);

        return $replacement;
    }

    private static function moveChildren(DOMElement $source, DOMElement $target): void
    {
        while ($source->firstChild !== null) {
            $target->appendChild($source->firstChild);
        }
    }

    private static function directElement(DOMElement $parent, string $tag): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === $tag) {
                return $child;
            }
        }

        return null;
    }

    /** @return list<DOMNode> */
    private static function significantChildren(DOMElement $parent): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMComment || ($child instanceof DOMText && trim($child->data) === '')) {
                continue;
            }
            $children[] = $child;
        }

        return $children;
    }
}
