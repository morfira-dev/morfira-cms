<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

/** @phpstan-import-type Export from WordPressExportSource */
interface WordPressStudioRestSource
{
    /**
     * Read the public metadata snapshot for an approved WordPress REST API URL.
     *
     * @return Export
     * @throws \InvalidArgumentException For invalid or unsafe URLs.
     * @throws \RuntimeException For failed remote reads.
     */
    public function readPublic(string $apiUrl): array;
}
