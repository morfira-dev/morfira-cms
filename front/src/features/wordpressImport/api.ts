import { apiFetch, readApiError } from '../../api/http'
import type { components, operations } from '../../api/schema'

export type WordPressImportReport = components['schemas']['WordPressImportReport']
export type WordPressImportMode = WordPressImportReport['mode']
export type WordPressImportRunCollection = components['schemas']['WordPressImportRunCollection']
export type WordPressImportRun = WordPressImportRunCollection['items'][number]
export type WordPressImportRunQuery = NonNullable<operations['listWordPressImportRuns']['parameters']['query']> & { page: number }
type WordPressRestImportRequest = operations['importWordPressRest']['requestBody']['content']['application/json']

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

export async function importWordPressRest(
  apiUrl: string,
  source: string,
  mode: WordPressImportMode,
  signal: AbortSignal,
  { selectedIds, downloadMedia, allowedMediaHosts }: {
    selectedIds?: readonly string[]
    downloadMedia?: boolean
    allowedMediaHosts?: readonly string[]
  } = {},
): Promise<WordPressImportReport> {
  const body: WordPressRestImportRequest = { apiUrl, source, mode }
  if (mode === 'apply' && selectedIds !== undefined) body.selectedIds = selectedIds.join(',')
  if (downloadMedia) {
    body.downloadMedia = true
    if (allowedMediaHosts?.length) body.allowedMediaHosts = [...allowedMediaHosts]
  }

  const response = await apiFetch(`${importPath}/rest`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
    signal,
  })
  return readImportResponse<WordPressImportReport>(response)
}

export async function listWordPressImportRuns({ page, source, input, scope }: WordPressImportRunQuery, signal: AbortSignal) {
  const params = new URLSearchParams({ page: String(page) })
  if (source !== undefined) params.set('source', source)
  if (input !== undefined) params.set('input', input)
  if (scope !== undefined) params.set('scope', scope)
  const response = await apiFetch(`${importPath}/runs?${params}`, { signal })
  return readImportResponse<WordPressImportRunCollection>(response)
}

export async function getWordPressImportRun(id: string, signal: AbortSignal) {
  const response = await apiFetch(`${importPath}/runs/${encodeURIComponent(id)}`, { signal })
  return readImportResponse<WordPressImportReport>(response)
}
