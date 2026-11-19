<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

final class ImportedRedirectSource
{
    public static function fromUrl(string $url): ?string
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1 || str_starts_with($url, '//')) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }
        if (isset($parts['scheme']) && (!in_array(strtolower($parts['scheme']), ['http', 'https'], true) || !isset($parts['host']))) {
            return null;
        }

        return self::fromRequest($parts['path'] ?? '/', $parts['query'] ?? null);
    }

    public static function fromRequest(string $path, ?string $query): ?string
    {
        if ($path === '/') {
            return self::isLegacyQuery($query) ? '/?'.$query : null;
        }
        if (strlen($path) > 1536 || preg_match('/%(?![a-f0-9]{2})|%(?:2f|5c)/i', $path) === 1) {
            return null;
        }

        $path = rtrim(rawurldecode($path), '/');
        if (strlen($path) > 512 || preg_match('~\A(?:/[\p{L}\p{N}\p{M}._\x7e-]+)+\z~u', $path) !== 1) {
            return null;
        }
        $segments = explode('/', substr($path, 1));
        if (in_array('.', $segments, true) || in_array('..', $segments, true)
            || in_array(strtolower($segments[0]), ['admin', 'api', 'media', 'health', 'ready', 'robots.txt', 'sitemap.xml', '404'], true)
            || str_starts_with($segments[0], '_')) {
            return null;
        }

        return $path;
    }

    public static function isLegacyQuery(?string $query): bool
    {
        return $query !== null && preg_match('/\A(?:p|page_id)=[1-9][0-9]{0,19}\z/', $query) === 1;
    }
}
