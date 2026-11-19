<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressMediaHosts;
use Cms\Content\Application\Import\WordPressRemoteMedia;
use Cms\Content\Application\Import\WordPressRemoteMediaFailure;
use Cms\Media\Application\UploadImage\UploadImage;

final class WordPressRemoteMediaDownloader implements WordPressRemoteMedia
{
    private const int MAX_HEADER_BYTES = 32 * 1024;

    public function assertUrl(string $apiUrl, string $attachmentUrl, array $allowedHosts = []): void
    {
        $this->attachment($apiUrl, $attachmentUrl, $allowedHosts);
    }

    public function download(string $apiUrl, string $attachmentUrl, \Closure $consume, array $allowedHosts = []): mixed
    {
        [$host, $url, $name] = $this->attachment($apiUrl, $attachmentUrl, $allowedHosts);
        if (!extension_loaded('curl')) {
            throw new WordPressRemoteMediaFailure('WordPress media download requires PHP cURL.');
        }

        try {
            $pins = WordPressRestEndpoint::resolveHostPins($host);
        } catch (\RuntimeException $exception) {
            throw new WordPressRemoteMediaFailure($exception->getMessage(), previous: $exception);
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new WordPressRemoteMediaFailure('Could not initialize the WordPress attachment request.');
        }

        $temporary = tmpfile();
        if ($temporary === false) {
            throw new WordPressRemoteMediaFailure('Could not stage the WordPress attachment.');
        }

        try {
            $this->transfer($handle, $pins, $temporary);
            $path = stream_get_meta_data($temporary)['uri'] ?? null;
            if (!is_string($path) || !fflush($temporary)) {
                throw new WordPressRemoteMediaFailure('Could not stage the WordPress attachment.');
            }

            return $consume($path, $name);
        } finally {
            fclose($temporary);
        }
    }

    /** @param resource $temporary */
    private function transfer(\CurlHandle $handle, string $pins, $temporary): void
    {
        $bytes = $headerBytes = 0;
        $bodyOverflow = $headerOverflow = false;
        if (!curl_setopt_array($handle, [
            CURLOPT_PROTOCOLS_STR => 'https',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$pins],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Morfira CMS WordPress Import',
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $handle, string $line) use (&$headerBytes, &$headerOverflow): int {
                $headerBytes += strlen($line);
                if ($headerBytes > self::MAX_HEADER_BYTES) {
                    $headerOverflow = true;
                    return 0;
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function (\CurlHandle $handle, string $chunk) use (&$bytes, &$bodyOverflow, $temporary): int {
                $length = strlen($chunk);
                if ($bytes + $length > UploadImage::MAX_BYTES) {
                    $bodyOverflow = true;
                    return 0;
                }
                $written = @fwrite($temporary, $chunk);
                if ($written !== $length) {
                    return 0;
                }
                $bytes += $written;

                return $written;
            },
        ])) {
            throw new WordPressRemoteMediaFailure('Could not configure the WordPress attachment request safely.');
        }

        $result = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($status !== 0 && $status !== 200) {
            throw new WordPressRemoteMediaFailure(sprintf('The WordPress attachment returned HTTP %d.', $status));
        }
        if ($bodyOverflow) {
            throw new \InvalidArgumentException('The WordPress attachment exceeds the image size limit.');
        }
        if ($headerOverflow) {
            throw new WordPressRemoteMediaFailure('The WordPress attachment response has too many headers.');
        }
        if ($result === false || $status !== 200) {
            throw new WordPressRemoteMediaFailure('Could not read the WordPress attachment. Check the connection and TLS certificate.');
        }
        if ($bytes === 0) {
            throw new \InvalidArgumentException('The WordPress attachment is empty.');
        }
    }

    /**
     * @param list<string> $allowedHosts
     * @return array{string,string,string}
     */
    private function attachment(string $apiUrl, string $attachmentUrl, array $allowedHosts): array
    {
        $endpoint = WordPressRestEndpoint::fromUrl($apiUrl);
        $allowedHosts = WordPressMediaHosts::normalize($allowedHosts);
        $parts = strlen($attachmentUrl) <= 4096 && preg_match('/[\x00-\x20\x7f\\\\]/', $attachmentUrl) === 0 ? parse_url($attachmentUrl) : false;
        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('WordPress attachments must use HTTPS on the default port.');
        }
        $host = strtolower($parts['host'] ?? '');
        if ($host !== $endpoint->host && !in_array($host, $allowedHosts, true)) {
            throw new \InvalidArgumentException('The WordPress attachment host is not allowed.');
        }

        $path = $parts['path'] ?? '';
        $decodedPath = rawurldecode($path);
        $name = basename($decodedPath);
        if (!str_starts_with($path, '/') || str_ends_with($path, '/')
            || preg_match('/%(?![a-f0-9]{2})|%(?:2f|5c)/i', $path) === 1
            || preg_match('/[\x00-\x1f\x7f\\\\]/u', $decodedPath) !== 0
            || array_intersect(explode('/', $decodedPath), ['.', '..']) !== []
            || trim($name) === '' || mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('The WordPress attachment has an invalid file path.');
        }

        $url = 'https://'.$host.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');

        return [$host, $url, $name];
    }
}
