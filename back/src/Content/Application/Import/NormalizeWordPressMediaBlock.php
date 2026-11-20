<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use DOMComment;
use DOMElement;
use DOMText;

final class NormalizeWordPressMediaBlock
{
    public static function normalize(DOMElement $block): void
    {
        $classes = self::classes($block);
        foreach (['audio', 'video'] as $type) {
            if (in_array('wp-block-'.$type, $classes, true)) {
                self::normalizePlayers($block, $type);

                return;
            }
        }
        if (in_array('wp-block-file', $classes, true)) {
            self::normalizeFile($block);
        } elseif (in_array('wp-block-embed', $classes, true)) {
            self::normalizeEmbed($block);
        }
    }

    private static function normalizePlayers(DOMElement $block, string $type): void
    {
        $players = $block->tagName === $type ? [$block] : iterator_to_array(self::descendants($block, $type, ['div', 'p']), false);
        foreach ($players as $player) {
            $url = self::playerUrl($player);
            if ($url === null) {
                continue;
            }

            $label = $url;
            foreach (self::descendants($player, 'a', ['source']) as $fallback) {
                if (trim($fallback->getAttribute('href')) === $url && ($text = self::plainText($fallback)) !== null && $text !== '') {
                    $label = $text;
                    break;
                }
            }
            self::replaceWithLink($player, $url, $label);
        }
    }

    private static function playerUrl(DOMElement $player): ?string
    {
        $url = self::safeUrl($player->getAttribute('src'));
        if ($url !== null) {
            return $url;
        }
        // The legacy HTML parser may nest consecutive HTML5 source elements.
        foreach (self::descendants($player, 'source', ['source']) as $source) {
            if (($url = self::safeUrl($source->getAttribute('src'))) !== null) {
                return $url;
            }
        }

        return null;
    }

    private static function normalizeFile(DOMElement $block): void
    {
        $preview = null;
        foreach ($block->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($child->tagName === 'a' && self::safeUrl($child->getAttribute('href')) !== null && trim($child->textContent) !== '') {
                return;
            }
            if ($child->tagName === 'object' && in_array('wp-block-file__embed', self::classes($child), true)) {
                $preview ??= $child;
            }
        }
        if ($preview !== null && ($url = self::safeUrl($preview->getAttribute('data'))) !== null) {
            self::replaceWithLink($preview, $url, $url);
        }
    }

    private static function normalizeEmbed(DOMElement $block): void
    {
        foreach (iterator_to_array($block->childNodes) as $child) {
            if (!$child instanceof DOMElement || $child->tagName !== 'div' || !in_array('wp-block-embed__wrapper', self::classes($child), true)) {
                continue;
            }
            $url = self::safeUrl(self::plainText($child) ?? '');
            if ($url !== null) {
                self::replaceWithLink($child, $url, $url);
            }
        }
    }

    /**
     * @param list<string> $containers
     * @return \Generator<int,DOMElement>
     */
    private static function descendants(DOMElement $element, string $tag, array $containers): \Generator
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($child->tagName === $tag) {
                yield $child;
            }
            if (in_array($child->tagName, $containers, true)) {
                yield from self::descendants($child, $tag, $containers);
            }
        }
    }

    private static function plainText(DOMElement $element): ?string
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMText && !$child instanceof DOMComment) {
                return null;
            }
        }

        return trim($element->textContent);
    }

    private static function replaceWithLink(DOMElement $element, string $url, string $label): void
    {
        $document = $element->ownerDocument;
        if ($document === null) {
            return;
        }

        $link = $document->createElement('a');
        $link->setAttribute('href', $url);
        $link->appendChild($document->createTextNode($label));
        if ($element->parentNode instanceof DOMElement && $element->parentNode->tagName === 'p') {
            $element->replaceWith($link);

            return;
        }

        $paragraph = $document->createElement('p');
        $paragraph->appendChild($link);
        $element->replaceWith($paragraph);
    }

    /** @return list<string> */
    private static function classes(DOMElement $element): array
    {
        return preg_split('/\s+/', $element->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function safeUrl(string $value): ?string
    {
        $url = trim($value);
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($url);

        return isset($parts['host'], $parts['scheme']) && !isset($parts['user']) && !isset($parts['pass'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true) ? $url : null;
    }
}
