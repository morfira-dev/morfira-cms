<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

interface ImportedRedirectRepository
{
    /** @return array{type:PublicationTarget,id:string}|null */
    public function owner(string $source): ?array;

    /** Return false if another operation already claimed this source. */
    public function register(string $source, PublicationTarget $type, string $targetId): bool;

    public function activeTargetPath(string $source): ?string;
}
