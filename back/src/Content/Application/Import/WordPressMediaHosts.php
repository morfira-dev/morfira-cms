<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

final class WordPressMediaHosts
{
    /**
     * @param list<string> $hosts
     * @return list<string>
     */
    public static function normalize(array $hosts): array
    {
        $normalized = [];
        foreach ($hosts as $host) {
            $host = strtolower($host);
            if (strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
                || preg_match('/\.[a-z][a-z0-9-]*\z/', $host) !== 1
                || preg_match('/\.(?:localhost|local|internal|lan|intranet|onion|invalid|home\.arpa)\z/', $host) === 1) {
                throw new \InvalidArgumentException('WordPress media hosts must be exact public DNS hostnames.');
            }
            $normalized[$host] = true;
        }

        return array_keys($normalized);
    }
}
