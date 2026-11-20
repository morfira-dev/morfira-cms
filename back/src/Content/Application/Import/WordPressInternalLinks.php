<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

final class WordPressInternalLinks
{
    private const int MAX_RICH_TEXT_CHARACTERS = 20_000;

    /**
     * @param list<array{url:string,path:?string}> $items
     * @return array<string,string>
     */
    public static function index(?string $sourceUrl, array $items): array
    {
        $source = self::absoluteHttpParts($sourceUrl ?? '');
        if ($source === null) {
            return [];
        }

        $links = [];
        $seen = [];
        foreach ($items as $item) {
            $parts = self::absoluteHttpParts($item['url']);
            if ($parts === null || $parts['host'] !== $source['host'] || ($parts['port'] ?? null) !== ($source['port'] ?? null)
                || isset($parts['fragment']) || ($key = self::permalinkKey($parts)) === null) {
                continue;
            }
            if (isset($seen[$key])) {
                unset($links[$key]);
                continue;
            }

            $seen[$key] = true;
            $target = $item['path'];
            if ($target !== null && preg_match('~\A/(?:[a-z0-9-]+(?:/[a-z0-9-]+)*)?\z~', $target) === 1) {
                $links[$key] = $target;
            }
        }

        return $links;
    }

    /** @param array<string,string> $links */
    public static function rewrite(string $html, array $links): string
    {
        if ($links === [] || !str_contains($html, ' href="')) {
            return $html;
        }

        $sanitizedLength = mb_strlen($html);
        // The rich-text sanitizer emits escaped text and double-quoted non-empty attributes.
        $updated = preg_replace_callback('~<a(?: [a-z]+(?:="[^"]*")?)*>~', static function (array $anchor) use ($links, &$sanitizedLength): string {
            return preg_replace_callback('~ ([a-z]+)="([^"]*)"~', static function (array $attribute) use ($links, &$sanitizedLength): string {
                if ($attribute[1] !== 'href') {
                    return $attribute[0];
                }
                $url = html_entity_decode($attribute[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $parts = self::absoluteHttpParts($url);
                $key = $parts === null ? null : self::permalinkKey($parts);
                if ($key === null || !isset($links[$key])) {
                    return $attribute[0];
                }

                $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
                // Count the fragment in its original escaped form, since saving sanitizes it again.
                $sanitizedLength += strlen($links[$key]) - mb_strlen($url) + mb_strlen($fragment);

                return ' href="'.htmlspecialchars($links[$key].$fragment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
            }, $anchor[0]) ?? $anchor[0];
        }, $html);

        return $updated !== null && $sanitizedLength <= self::MAX_RICH_TEXT_CHARACTERS ? $updated : $html;
    }

    /** @return array{host:string,port?:int,path?:string,query?:string,fragment?:string}|null */
    private static function absoluteHttpParts(string $url): ?array
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'], $parts['scheme']) || isset($parts['user']) || isset($parts['pass'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        $parts['host'] = strtolower($parts['host']);

        return $parts;
    }

    /** @param array{host:string,port?:int,path?:string,query?:string} $parts */
    private static function permalinkKey(array $parts): ?string
    {
        $path = $parts['path'] ?? '/';
        if (!str_starts_with($path, '/') || str_contains($path, '//') || preg_match('/%(?![a-f0-9]{2})|%(?:2f|5c)/i', $path) === 1) {
            return null;
        }
        $decoded = rawurldecode($path);
        if (preg_match('/[\x00-\x1f\x7f]/', $decoded) === 1 || array_intersect(explode('/', $decoded), ['.', '..']) !== []) {
            return null;
        }
        $query = $parts['query'] ?? null;
        if ($query !== null && ($path !== '/' || preg_match('/\A(?:p|page_id)=[1-9][0-9]{0,19}\z/', $query) !== 1)) {
            return null;
        }

        return $parts['host'].':'.($parts['port'] ?? '').(rtrim($path, '/') ?: '/').($query === null ? '' : '?'.$query);
    }
}
