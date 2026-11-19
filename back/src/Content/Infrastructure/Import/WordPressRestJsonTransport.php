<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

use Cms\Content\Application\Import\WordPressRestCredentials;

interface WordPressRestJsonTransport
{
    /**
     * @param array<string,string|int> $query
     * @return array{body:list<array<string,mixed>>,totalPages:int}
     */
    public function get(string $baseUrl, string $resource, array $query, ?WordPressRestCredentials $credentials = null): array;
}
