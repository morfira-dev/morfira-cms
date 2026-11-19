<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

final readonly class WordPressRestEndpoint
{
    private function __construct(public string $url, public string $host)
    {
    }

    public static function fromUrl(string $url): self
    {
        $parts = strlen($url) <= 512 && preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 0 ? parse_url($url) : false;
        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ($parts['port'] ?? 443) !== 443) {
            throw self::invalidUrl();
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || preg_match('/\.[a-z][a-z0-9-]*\z/', $host) !== 1
            || preg_match('!\A(?:/[a-zA-Z0-9._~-]+)*/wp-json/wp/v2/?\z!', $path) !== 1
            || array_intersect(explode('/', $path), ['.', '..']) !== []) {
            throw self::invalidUrl();
        }

        return new self('https://'.$host.rtrim($path, '/'), $host);
    }

    /** Resolve and validate every address before pinning the HTTPS connection. */
    public function resolvePins(): string
    {
        return self::resolveHostPins($this->host);
    }

    /** Build cURL pins for an already validated HTTPS hostname. */
    public static function resolveHostPins(string $host): string
    {
        $records = @dns_get_record($host.'.', DNS_A | DNS_AAAA);
        $addresses = [];
        if ($records !== false) {
            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address)) {
                    $addresses[] = $address;
                }
            }
        }
        $addresses = self::publicAddresses(array_values(array_unique($addresses)));
        $pins = array_map(static fn (string $ip): string => str_contains($ip, ':') ? '['.$ip.']' : $ip, $addresses);

        return $host.':443:'.implode(',', $pins);
    }

    /**
     * @param list<string> $addresses
     * @return non-empty-list<string>
     */
    public static function publicAddresses(array $addresses): array
    {
        if ($addresses === [] || count($addresses) > 32) {
            throw new \RuntimeException('The WordPress host did not resolve to an acceptable public address.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new \RuntimeException('The WordPress host must resolve only to public addresses.');
            }
            $bytes = inet_pton($address);
            if ($bytes === false || !self::isPublicUnicast($bytes)) {
                throw new \RuntimeException('The WordPress host must resolve only to public addresses.');
            }
        }

        return $addresses;
    }

    private static function isPublicUnicast(string $bytes): bool
    {
        if (strlen($bytes) === 4) {
            // The global-range filter also accepts some multicast addresses.
            return ord($bytes[0]) < 224 && !str_starts_with($bytes, "\xc0\x58\x63");
        }

        // Global unicast allocation, minus transition and protocol-assignment prefixes.
        return (ord($bytes[0]) & 0xe0) === 0x20
            && !str_starts_with($bytes, "\x20\x02")
            && !str_starts_with($bytes, "\x3f\xff")
            && !(str_starts_with($bytes, "\x20\x01") && ord($bytes[2]) <= 1);
    }

    private static function invalidUrl(): \InvalidArgumentException
    {
        return new \InvalidArgumentException('Provide an HTTPS WordPress REST namespace URL ending in /wp-json/wp/v2.');
    }
}
