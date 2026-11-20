import type { WordPressImportReport } from './api'

export type WordPressImportItem = WordPressImportReport['items'][number]

interface ImportItemFilters {
  search: string
  type: string
  state: string
}

export function filterImportItems(items: readonly WordPressImportItem[], { search, type, state }: ImportItemFilters) {
  const query = search.trim().toLowerCase()

  return items.filter((item) => (
    (!type || item.type === type)
      && (!state || item.state === state)
      && (!query || [item.title, item.id, item.sourcePath, item.targetPath].some((value) => value?.toLowerCase().includes(query)))
  ))
}
