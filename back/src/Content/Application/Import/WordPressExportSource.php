<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

/**
 * @phpstan-import-type SourceItem from ImportWordPressDrafts
 * @phpstan-import-type Term from ImportWordPressDrafts
 * @phpstan-import-type Attachment from ImportWordPressMedia
 * @phpstan-import-type Author from ImportWordPressAuthors
 * @phpstan-type Export array{items:list<SourceItem>,skippedTypes:array<string,int>,attachments:list<Attachment>,terms:list<Term>,authors:list<Author>,sourceUrl:?string}
 */
interface WordPressExportSource
{
    public const int MAX_BYTES = 8 * 1024 * 1024;
    public const int MAX_ITEMS = 5_000;

    /** @return Export */
    public function read(string $path): array;
}
