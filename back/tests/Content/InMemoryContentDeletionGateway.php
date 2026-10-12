<?php

declare(strict_types=1);

namespace Cms\Tests\Content;

use Cms\Content\Application\ContentDeletionGateway;

final class InMemoryContentDeletionGateway implements ContentDeletionGateway
{
    /** @var list<string> */
    public array $deletedPages = [];

    /** @var list<string> */
    public array $deletedEntries = [];

    /** @var array<string, list<array{id:string,title:string,fieldKey:string}>> */
    public array $references = [];

    public function deletePage(string $pageId): void
    {
        $this->deletedPages[] = $pageId;
    }

    public function deleteEntry(string $entryId): void
    {
        $this->deletedEntries[] = $entryId;
    }

    public function referencesToEntry(string $entryId): array
    {
        return $this->references[$entryId] ?? [];
    }
}
