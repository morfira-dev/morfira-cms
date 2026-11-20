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
 * @phpstan-type Report array{mode:'apply'|'dry-run',input:'wxr',source:string,scope?:'selection',counts:object,skippedTypes:object,items:list<ReportItem>,media:list<MediaOutcome>,taxonomy:TaxonomyOutcomes,authors:list<AuthorOutcome>}
 */
final readonly class ImportWordPressExport
{
    public function __construct(private WordPressExportSource $exports, private ImportWordPressDrafts $drafts)
    {
    }

    /** @return Report */
    public function __invoke(string $path, string $source, bool $apply, ?string $selectedIds = null): array
    {
        if (!ImportWordPressDrafts::validSourceKey($source)) {
            throw new \InvalidArgumentException('Source must be a lowercase identifier of at most 80 characters.');
        }
        if ($selectedIds !== null && !$apply) {
            throw new \InvalidArgumentException('Selected IDs are accepted only when applying an import.');
        }

        $selection = $selectedIds === null ? null : $this->parseSelection($selectedIds);
        $export = $this->exports->read($path);
        $items = $selection === null ? $export['items'] : $this->selectedItems($export['items'], $selection);
        $report = ($this->drafts)(
            $source,
            $items,
            $apply,
            $export['terms'],
            $export['attachments'],
            null,
            $export['authors'],
            $export['sourceUrl'],
            scopeDependencies: $selection !== null,
        );

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
            'input' => 'wxr',
            'source' => $source,
            'counts' => (object) array_count_values(array_column($report['items'], 'state')),
            'skippedTypes' => (object) ($selection === null ? $export['skippedTypes'] : []),
        ] + ($selection === null ? [] : ['scope' => 'selection']) + $report;
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
