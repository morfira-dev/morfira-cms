<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use DOMElement;
use DOMText;

final class WordPressLegacyGallery
{
    private const int MAX_IMAGES = 20;
    private const string IMAGE_ID = '/\A[1-9][0-9]{0,19}\z/';
    private const array ATTRIBUTES = ['ids', 'include', 'size', 'columns', 'link', 'itemtag', 'icontag', 'captiontag'];

    public static function expand(DOMText $text): void
    {
        $source = $text->data;
        $document = $text->ownerDocument;
        if ($document === null || $text->parentNode === null || str_contains($source, '[/gallery]')
            || !preg_match_all('/(?<!\[)\[gallery\s+([^\[\]]{1,1024})\](?!\])/', $source, $shortcodes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return;
        }

        $replacement = null;
        $position = 0;
        foreach ($shortcodes as [[$shortcode, $offset], [$attributes]]) {
            $ids = self::explicitIds($attributes);
            if ($ids === []) {
                continue;
            }

            $replacement ??= $document->createDocumentFragment();
            if ($offset > $position) {
                $replacement->appendChild($document->createTextNode(substr($source, $position, $offset - $position)));
            }
            foreach ($ids as $id) {
                $image = $document->createElement('img');
                $image->setAttribute('class', 'wp-image-'.$id);
                $replacement->appendChild($image);
            }
            $position = $offset + strlen($shortcode);
        }

        if ($replacement !== null) {
            if ($position < strlen($source)) {
                $replacement->appendChild($document->createTextNode(substr($source, $position)));
            }
            $text->replaceWith($replacement);
        }
    }

    public static function imageId(DOMElement $image): ?string
    {
        $id = $image->getAttribute('data-id');
        if ($image->tagName !== 'img' || preg_match(self::IMAGE_ID, $id) !== 1) {
            return null;
        }

        for ($parent = $image->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if (preg_match('/(?:^|\s)wp-block-gallery(?:\s|$)/', $parent->getAttribute('class')) === 1) {
                return $id;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function explicitIds(string $attributes): array
    {
        $attributes = trim($attributes);
        if (str_ends_with($attributes, '/')) {
            $attributes = rtrim(substr($attributes, 0, -1));
        }

        $values = [];
        $offset = 0;
        while ($offset < strlen($attributes)) {
            if (preg_match('/\G\s*([a-z]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([a-z0-9_,.-]+))(?=\s|\z)/i', $attributes, $match, PREG_UNMATCHED_AS_NULL, $offset) !== 1) {
                return [];
            }

            $name = strtolower($match[1]);
            if (!in_array($name, self::ATTRIBUTES, true) || isset($values[$name])) {
                return [];
            }
            $values[$name] = $match[2] ?? $match[3] ?? $match[4] ?? '';
            $offset += strlen($match[0]);
        }

        if (isset($values['ids'], $values['include'])) {
            return [];
        }
        $ids = array_map(trim(...), explode(',', $values['ids'] ?? $values['include'] ?? '', self::MAX_IMAGES + 1));
        if (count($ids) > self::MAX_IMAGES || array_any($ids, static fn (string $id): bool => preg_match(self::IMAGE_ID, $id) !== 1)) {
            return [];
        }

        return $ids;
    }
}
