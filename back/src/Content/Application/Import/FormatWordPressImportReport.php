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
 * @phpstan-type Report array{mode:'apply'|'dry-run',input:'wxr'|'rest',source:string,scope?:'selection',counts:object,skippedTypes:object,items:list<ReportItem>,media:list<MediaOutcome>,taxonomy:TaxonomyOutcomes,authors:list<AuthorOutcome>}
 */
final class FormatWordPressImportReport
{
    /**
     * @param ImportReport $outcomes
     * @param 'wxr'|'rest' $input
     * @param array<string,int> $skippedTypes
     * @param list<SourceItem> $itemsWithTitles
     * @return Report
     */
    public static function create(array $outcomes, string $source, string $input, bool $apply, array $skippedTypes, array $itemsWithTitles = [], bool $selected = false): array
    {
        $titles = [];
        foreach ($itemsWithTitles as $item) {
            if (!$item['passwordProtected'] && !($item['restrictedSource'] ?? false) && in_array($item['status'], ['publish', 'draft', 'pending', 'future'], true) && $item['title'] !== '') {
                $titles[$item['id']] = mb_substr($item['title'], 0, PageTitle::MAX_LENGTH);
            }
        }
        foreach ($outcomes['items'] as $index => $item) {
            if (isset($titles[$item['id']])) {
                $outcomes['items'][$index]['title'] = $titles[$item['id']];
            }
        }

        return [
            'mode' => $apply ? 'apply' : 'dry-run',
            'input' => $input,
            'source' => $source,
            'counts' => (object) array_count_values(array_column($outcomes['items'], 'state')),
            'skippedTypes' => (object) ($selected ? [] : $skippedTypes),
        ] + ($selected ? ['scope' => 'selection'] : []) + $outcomes;
    }
}
