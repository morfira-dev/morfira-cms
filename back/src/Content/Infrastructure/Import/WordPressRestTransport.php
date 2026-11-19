<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

final class WordPressRestTransport implements WordPressRestJsonTransport
{
    private const int MAX_BODY_BYTES = 2 * 1024 * 1024;
    private const int MAX_HEADER_BYTES = 32 * 1024;
    private const array RESOURCES = ['posts', 'pages', 'media', 'categories', 'tags', 'users'];

    public static function normalizeBaseUrl(string $url): string
    {
        return WordPressRestEndpoint::fromUrl($url)->url;
    }

    public function get(string $baseUrl, string $resource, array $query, ?WordPressRestCredentials $credentials = null): array
    {
        $endpoint = WordPressRestEndpoint::fromUrl($baseUrl);
        if (!in_array($resource, self::RESOURCES, true)) {
            throw new \InvalidArgumentException('Unsupported WordPress REST collection.');
        }
        if ($credentials !== null && (!in_array($resource, ['posts', 'pages'], true) || ($query['context'] ?? null) !== 'edit')) {
            throw new \InvalidArgumentException('WordPress credentials are only allowed for edit-context posts and pages.');
        }
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('WordPress REST import requires PHP cURL.');
        }
        $pins = $endpoint->resolvePins();
        $url = $endpoint->url.'/'.$resource.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Could not initialize the WordPress REST connection.');
        }

        $body = '';
        $totalPages = null;
        $headerBytes = 0;
        $overflow = false;
        $ambiguousPagination = false;
        $auth = $credentials === null ? [] : [
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERNAME => $credentials->username,
            CURLOPT_PASSWORD => $credentials->applicationPassword(),
        ];
        if (!curl_setopt_array($handle, [
            CURLOPT_PROTOCOLS_STR => 'https',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_UNRESTRICTED_AUTH => false,
            CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$pins],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'Morfira CMS WordPress Import',
            CURLOPT_WRITEFUNCTION => static function (\CurlHandle $handle, string $chunk) use (&$body, &$overflow): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BODY_BYTES) {
                    $overflow = true;
                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $handle, string $line) use (&$totalPages, &$headerBytes, &$overflow, &$ambiguousPagination): int {
                $headerBytes += strlen($line);
                if ($headerBytes > self::MAX_HEADER_BYTES) {
                    $overflow = true;
                    return 0;
                }
                if (str_starts_with($line, 'HTTP/')) {
                    $totalPages = null;
                    $ambiguousPagination = false;
                } elseif (strncasecmp($line, 'X-WP-TotalPages:', 16) === 0) {
                    $ambiguousPagination = $totalPages !== null;
                    $totalPages = trim(substr($line, 16));
                }

                return strlen($line);
            },
        ] + $auth)) {
            throw new \RuntimeException('Could not configure the WordPress REST connection safely.');
        }

        $result = curl_exec($handle);
        if ($overflow) {
            throw new \RuntimeException('The WordPress REST response exceeds the allowed size.');
        }
        if ($result === false) {
            throw new \RuntimeException('Could not read the WordPress REST response. Check the connection and TLS certificate.');
        }
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($credentials !== null && ($status === 401 || $status === 403)) {
            throw new \RuntimeException('WordPress denied the REST request. Check the Application Password and account permissions.');
        }
        if ($status !== 200) {
            throw new \RuntimeException(sprintf('The WordPress REST collection %s returned HTTP %d.', $resource, $status));
        }
        $contentType = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        if (!is_string($contentType) || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new \RuntimeException('The WordPress REST response must be JSON.');
        }
        if ($ambiguousPagination || !is_string($totalPages) || preg_match('/\A[0-9]{1,9}\z/', $totalPages) !== 1) {
            throw new \RuntimeException('The WordPress REST response is missing valid pagination metadata.');
        }
        /** @var list<array<string,mixed>> $items */
        $items = $this->decode($body);

        return ['body' => $items, 'totalPages' => (int) $totalPages];
    }

    /** @return list<array<string,mixed>> */
    private function decode(string $body): array
    {
        try {
            $value = json_decode($body, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            throw new \RuntimeException('The WordPress REST response contains invalid JSON.');
        }
        if (!str_starts_with(ltrim($body), '[') || !is_array($value) || !array_is_list($value) || count($value) > 100) {
            throw new \RuntimeException('The WordPress REST response is not a bounded collection.');
        }
        $items = [];
        foreach ($value as $item) {
            if (!is_array($item) || array_is_list($item)
                || count(array_filter(array_keys($item), is_string(...))) !== count($item)) {
                throw new \RuntimeException('The WordPress REST collection contains an invalid item.');
            }
            /** @var array<string,mixed> $item */
            $items[] = $item;
        }

        return $items;
    }
}
