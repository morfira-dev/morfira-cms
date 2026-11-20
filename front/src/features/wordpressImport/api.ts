import { apiFetch, readApiError } from '../../api/http'
import type { components } from '../../api/schema'

export type WordPressImportReport = components['schemas']['WordPressImportReport']
export type WordPressImportMode = WordPressImportReport['mode']

export async function importWordPress(
  file: File,
  source: string,
  mode: WordPressImportMode,
  signal: AbortSignal,
  selectedIds?: readonly string[],
): Promise<WordPressImportReport> {
  const body = new FormData()
  body.set('file', file)
  body.set('source', source)
  body.set('mode', mode)
  if (mode === 'apply' && selectedIds !== undefined) body.set('selectedIds', selectedIds.join(','))

  const response = await apiFetch('/api/v1/admin/imports/wordpress', { method: 'POST', body, signal })
  if (!response.ok) throw new Error(await readApiError(response))

  return await response.json() as WordPressImportReport
}
