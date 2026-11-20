<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use Cms\Content\Domain\PageTitle;

/**
 * @phpstan-import-type ReportItem from ImportWordPressDrafts
 * @phpstan-import-type TaxonomyOutcomes from ImportWordPressDrafts
 * @phpstan-import-type MediaOutcome from ImportWordPressMedia
 * @phpstan-import-type AuthorOutcome from ImportWordPressAuthors
 * @phpstan-import-type SourceItem from ImportWordPressDrafts
 * @phpstan-import-type ImportReport from ImportWordPressDrafts
 * @phpstan-import-type Export from WordPressExportSource
 * @phpstan-type Report array{mode:'apply'|'dry-run',input:'wxr'|'rest',source:string,scope?:'selection',counts:object,skippedTypes:object,items:list<ReportItem>,media:list<MediaOutcome>,taxonomy:TaxonomyOutcomes,authors:list<AuthorOutcome>}
 */
final readonly class ImportWordPressExport
{
    public const string INVALID_MEDIA_HOSTS = 'Provide a list of up to eight WordPress media hostnames.';
    private const int MAX_MEDIA_HOSTS = 8;

    public function __construct(
        private WordPressExportSource $exports,
        private ImportWordPressDrafts $drafts,
        private WordPressImportRunHistory $history,
        private WordPressStudioRestSource $rest,
    ) {
    }

    /** @return Report */
    public function __invoke(string $path, string $source, bool $apply, ?string $selectedIds = null): array
    {
        $selection = $this->validateRequest($source, $apply, $selectedIds);

        return $this->import($this->exports->read($path), $source, $apply, $selection, 'wxr');
    }

    /**
     * @param list<string>|null $allowedMediaHosts
     * @return Report
     */
    public function fromRest(
        string $apiUrl,
        string $source,
        bool $apply,
        ?string $selectedIds = null,
        bool $downloadMedia = false,
        ?array $allowedMediaHosts = null,
    ): array {
        $selection = $this->validateRequest($source, $apply, $selectedIds);
        if (trim($apiUrl) === '' || strlen($apiUrl) > 512) {
            throw new \InvalidArgumentException('Provide a safe HTTPS WordPress REST API URL.');
        }
        if ($allowedMediaHosts !== null && !$downloadMedia) {
            throw new \InvalidArgumentException('Allowed media hosts require public image downloads.');
        }
        if (count($allowedMediaHosts ?? []) > self::MAX_MEDIA_HOSTS) {
            throw new \InvalidArgumentException(self::INVALID_MEDIA_HOSTS);
        }
        $allowedMediaHosts = WordPressMediaHosts::normalize($allowedMediaHosts ?? []);

        try {
            $export = $this->rest->readPublic($apiUrl);
        } catch (\InvalidArgumentException $error) {
            throw new \InvalidArgumentException('Provide a safe HTTPS WordPress REST API URL.', previous: $error);
        } catch (\RuntimeException $error) {
            throw new WordPressStudioRestReadFailed($error);
        }

        try {
            return $this->import($export, $source, $apply, $selection, 'rest', $downloadMedia ? $apiUrl : null, $allowedMediaHosts);
        } catch (WordPressRemoteMediaFailure $error) {
            throw new WordPressStudioRestReadFailed($error);
        }
    }

    /** @return ?array<int|string,true> */
    private function validateRequest(string $source, bool $apply, ?string $selectedIds): ?array
    {
        if (!ImportWordPressDrafts::validSourceKey($source)) {
            throw new \InvalidArgumentException('Source must be a lowercase identifier of at most 80 characters.');
        }
        if ($selectedIds !== null && !$apply) {
            throw new \InvalidArgumentException('Selected IDs are accepted only when applying an import.');
        }

        return $selectedIds === null ? null : $this->parseSelection($selectedIds);
    }

    /**
     * @param Export $export
     * @param ?array<int|string,true> $selection
     * @param 'wxr'|'rest' $input
     * @param list<string> $allowedMediaHosts
     * @return Report
     */
    private function import(array $export, string $source, bool $apply, ?array $selection, string $input, ?string $mediaApiUrl = null, array $allowedMediaHosts = []): array
    {
        $items = $selection === null ? $export['items'] : $this->selectedItems($export['items'], $selection);
        $selected = $selection !== null;

        return ($this->drafts)(
            $source,
            $items,
            $apply,
            $export['terms'],
            $export['attachments'],
            null,
            $export['authors'],
            $export['sourceUrl'],
            apiUrl: $mediaApiUrl,
            allowedMediaHosts: $allowedMediaHosts,
            scopeDependencies: $selected,
            includeDraftReferences: true,
            complete: function (array $outcomes) use ($source, $export, $items, $apply, $selected, $input): array {
                $report = $this->report($outcomes, $source, $export, $items, $apply, $selected, $input);
                if ($apply) {
                    $this->history->record($report);
                }

                return $report;
            },
            mediaBudget: $apply && $mediaApiUrl !== null ? new WordPressStudioMediaBudget() : null,
        );
    }

    /**
     * @param ImportReport $report
     * @param Export $export
     * @param list<SourceItem> $items
     * @param 'wxr'|'rest' $input
     * @return Report
     */
    private function report(array $report, string $source, array $export, array $items, bool $apply, bool $selected, string $input): array
    {
        $titles = [];
        foreach ($items as $item) {
            if (!$item['passwordProtected'] && !($item['restrictedSource'] ?? false) && in_array($item['status'], ['publish', 'draft', 'pending', 'future'], true) && $item['title'] !== '') {
                $titles[$item['id']] = mb_substr($item['title'], 0, PageTitle::MAX_LENGTH);
            }
        }
        foreach ($report['items'] as $index => $item) {
            if (isset($titles[$item['id']])) {
                $report['items'][$index]['title'] = $titles[$item['id']];
            }
        }

        return [
            'mode' => $apply ? 'apply' : 'dry-run',
            'input' => $input,
            'source' => $source,
            'counts' => (object) array_count_values(array_column($report['items'], 'state')),
            'skippedTypes' => (object) ($selected ? [] : $export['skippedTypes']),
        ] + ($selected ? ['scope' => 'selection'] : []) + $report;
    }

    /** @return array<int|string,true> */
    private function parseSelection(string $value): array
    {
        if (strlen($value) > WordPressExportSource::MAX_ITEMS * 21 - 1) {
            throw new \InvalidArgumentException('Select no more than 5,000 WordPress items.');
        }
        $ids = explode(',', $value, WordPressExportSource::MAX_ITEMS + 1);
        if (count($ids) > WordPressExportSource::MAX_ITEMS) {
            throw new \InvalidArgumentException('Select no more than 5,000 WordPress items.');
        }
        $selection = [];
        foreach ($ids as $id) {
            if (preg_match('/\A[1-9][0-9]{0,19}\z/', $id) !== 1 || isset($selection[$id])) {
                throw new \InvalidArgumentException('Selected IDs must be unique positive decimal WordPress item identifiers.');
            }
            $selection[$id] = true;
        }

        return $selection;
    }

    /**
     * @param list<SourceItem> $items
     * @param array<int|string,true> $selection
     * @return list<SourceItem>
     */
    private function selectedItems(array $items, array $selection): array
    {
        $selected = array_values(array_filter($items, static fn (array $item): bool => isset($selection[$item['id']])));
        if (count($selected) !== count($selection)) {
            throw new \InvalidArgumentException('Selected IDs must identify pages or posts in this WordPress export.');
        }

        return $selected;
    }
}
