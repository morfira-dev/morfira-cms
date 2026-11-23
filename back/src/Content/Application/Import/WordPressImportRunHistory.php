<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

/**
 * @phpstan-import-type Report from ImportWordPressExport
 * @phpstan-type RunSummary array{id:string,source:string,input:'wxr'|'rest',scope:'all'|'selection',channel:'studio'|'cli',completedAt:string,counts:\stdClass}
 * @phpstan-type SourceSummary array{source:string,runCount:int,studioRuns:int,cliRuns:int,latestRun:RunSummary}
 */
interface WordPressImportRunHistory
{
    /**
     * Persist the completed apply report inside the draft transaction.
     *
     * @param Report $report
     * @param 'studio'|'cli' $channel
     */
    public function record(array $report, string $channel = 'studio'): void;

    /**
     * @param 'wxr'|'rest'|null $input
     * @param 'all'|'selection'|null $scope
     * @param 'studio'|'cli'|null $channel
     * @return list<RunSummary>
     */
    public function latest(int $offset, int $limit, ?string $source = null, ?string $input = null, ?string $scope = null, ?string $channel = null): array;

    /** @return list<SourceSummary> */
    public function sources(int $offset, int $limit, ?string $prefix = null): array;

    public function report(string $id): ?\stdClass;
}
