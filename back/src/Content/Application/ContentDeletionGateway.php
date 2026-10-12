<?php

declare(strict_types=1);

namespace Cms\Content\Application;

interface ContentDeletionGateway
{
    public function deletePage(string $pageId): void;

    public function deleteEntry(string $entryId): void;

    /** @return list<array{id:string,title:string,fieldKey:string}> */
    public function referencesToEntry(string $entryId): array;
}
