<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

interface WordPressRemoteMedia
{
    /**
     * Validate the attachment URL without contacting the source.
     *
     * @param list<string> $allowedHosts
     */
    public function assertUrl(string $apiUrl, string $attachmentUrl, array $allowedHosts = []): void;

    /**
     * Provide a bounded download to the consumer while its temporary file exists.
     *
     * @template T
     * @param \Closure(string,string):T $consume
     * @param list<string> $allowedHosts
     * @return T
     */
    public function download(string $apiUrl, string $attachmentUrl, \Closure $consume, array $allowedHosts = []): mixed;
}
