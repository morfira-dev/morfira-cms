<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

/**
 * @phpstan-import-type Report from ImportWordPressExport
 * @phpstan-type RunSummary array{id:string,source:string,input:'wxr'|'rest',scope:'all'|'selection',completedAt:string,counts:\stdClass}
 */
interface WordPressImportRunHistory
{
    /**
     * Persist the completed apply report inside the draft transaction.
     *
     * @param Report $report
     */
    public function record(array $report): void;

    /**
     * @param 'wxr'|'rest'|null $input
     * @param 'all'|'selection'|null $scope
     * @return list<RunSummary>
     */
    public function latest(int $offset, int $limit, ?string $source = null, ?string $input = null, ?string $scope = null): array;

    public function report(string $id): ?\stdClass;
}
