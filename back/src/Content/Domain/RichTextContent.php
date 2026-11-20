<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final class RichTextContent
{
    public static function isVisible(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $text);

        return ($text !== null && $text !== '')
            || preg_match('~<(?:hr(?=[\s/>])|table(?=[\s>])|img\b[^>]*\ssrc="[^"]+")~i', $html) === 1;
    }
}
