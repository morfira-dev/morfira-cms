import type { WordPressImportReport } from './api'

export type WordPressImportItem = WordPressImportReport['items'][number]

export const importReviewIssues = ['inlineImages', 'featuredImage', 'author', 'redirect'] as const
export type ImportReviewIssue = (typeof importReviewIssues)[number]
export type ImportReviewFilter = '' | 'any' | ImportReviewIssue

const issueChecks: Record<ImportReviewIssue, (item: WordPressImportItem) => boolean> = {
  inlineImages: (item) => (item.images?.missingInline ?? 0) > 0,
  featuredImage: (item) => item.images?.featured === 'missing',
  author: (item) => item.author === 'missing',
  redirect: (item) => item.redirect === 'conflict' || item.redirect === 'unsupported',
}

interface ImportItemFilters {
  search: string
  type: string
  state: string
  issue?: ImportReviewFilter
}

export function countImportReviewIssues(items: readonly WordPressImportItem[]): Record<ImportReviewIssue | 'any', number> {
  const counts = { inlineImages: 0, featuredImage: 0, author: 0, redirect: 0, any: 0 }

  for (const item of items) {
    let affected = false
    for (const issue of importReviewIssues) {
      if (issueChecks[issue](item)) {
        counts[issue] += 1
        affected = true
      }
    }
    if (affected) counts.any += 1
  }

  return counts
}

export function filterImportItems(items: readonly WordPressImportItem[], { search, type, state, issue }: ImportItemFilters) {
  const query = search.trim().toLowerCase()
  const matchesIssue = issue === 'any'
    ? (item: WordPressImportItem) => importReviewIssues.some((candidate) => issueChecks[candidate](item))
    : issue && issueChecks[issue]

  return items.filter((item) => (
    (!type || item.type === type)
      && (!state || item.state === state)
      && (!matchesIssue || matchesIssue(item))
      && (!query || [item.title, item.id, item.sourcePath, item.targetPath].some((value) => value?.toLowerCase().includes(query)))
  ))
}
