import { apiFetch, readApiError } from '../../api/http'
import type { components } from '../../api/schema'

export type WordPressImportReport = components['schemas']['WordPressImportReport']
export type WordPressImportMode = WordPressImportReport['mode']
export type WordPressImportRunCollection = components['schemas']['WordPressImportRunCollection']
export type WordPressImportRun = WordPressImportRunCollection['items'][number]

const importPath = '/api/v1/admin/imports/wordpress'

async function readImportResponse<T>(response: Response): Promise<T> {
  if (!response.ok) throw new Error(await readApiError(response))

  return await response.json() as T
}

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

  const response = await apiFetch(importPath, { method: 'POST', body, signal })
  return readImportResponse<WordPressImportReport>(response)
}

export async function listWordPressImportRuns(page: number, signal: AbortSignal) {
  const response = await apiFetch(`${importPath}/runs?page=${page}`, { signal })
  return readImportResponse<WordPressImportRunCollection>(response)
}

export async function getWordPressImportRun(id: string, signal: AbortSignal) {
  const response = await apiFetch(`${importPath}/runs/${encodeURIComponent(id)}`, { signal })
  return readImportResponse<WordPressImportReport>(response)
}
