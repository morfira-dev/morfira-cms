<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

/**
 * @phpstan-import-type Export from WordPressExportSource
 * @phpstan-import-type Report from FormatWordPressImportReport
 */
final readonly class ImportWordPressCli
{
    public function __construct(
        private ImportWordPressDrafts $drafts,
        private WordPressImportRunHistory $history,
    ) {
    }

    /**
     * @param Export $export
     * @param list<string> $allowedMediaHosts
     * @return Report
     */
    public function __invoke(
        array $export,
        string $source,
        bool $apply,
        ?string $mediaDirectory,
        ?string $apiUrl,
        array $allowedMediaHosts,
        ?WordPressRestCredentials $credentials,
        bool $recordHistory,
    ): array {
        if ($recordHistory && (!$apply || $credentials !== null)) {
            throw new \InvalidArgumentException('History requires an apply without WordPress credentials.');
        }

        return ($this->drafts)(
            $source,
            $export['items'],
            $apply,
            $export['terms'],
            $export['attachments'],
            $mediaDirectory,
            $export['authors'],
            $export['sourceUrl'],
            $apiUrl,
            $allowedMediaHosts,
            $credentials,
            includeDraftReferences: $recordHistory,
            complete: function (array $outcomes) use ($export, $source, $apply, $apiUrl, $recordHistory): array {
                $report = FormatWordPressImportReport::create(
                    [
                        'items' => $outcomes['items'],
                        'media' => $outcomes['media'],
                        'taxonomy' => $outcomes['taxonomy'],
                        'authors' => $outcomes['authors'],
                    ],
                    $source,
                    $apiUrl === null ? 'wxr' : 'rest',
                    $apply,
                    $export['skippedTypes'],
                    $recordHistory ? $export['items'] : [],
                );
                if ($recordHistory) {
                    $this->history->record($report, 'cli');
                }

                return $report;
            },
        );
    }
}
