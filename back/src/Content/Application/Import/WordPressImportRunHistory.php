<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

/**
 * @phpstan-import-type Report from ImportWordPressExport
 * @phpstan-type RunSummary array{id:string,source:string,scope:'all'|'selection',completedAt:string,counts:\stdClass}
 */
interface WordPressImportRunHistory
{
    /**
     * Persist the completed apply report inside the draft transaction.
     *
     * @param Report $report
     */
    public function record(array $report): void;

    /** @return list<RunSummary> */
    public function latest(int $offset, int $limit): array;

    public function report(string $id): ?\stdClass;
}
