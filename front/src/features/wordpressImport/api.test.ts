import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { getWordPressImportRun, importWordPress, listWordPressImportRuns, type WordPressImportReport, type WordPressImportRunCollection } from './api'

afterEach(() => {
  vi.unstubAllGlobals()
  resetCsrfToken()
})

describe('importWordPress', () => {
  it.each([
    ['dry-run', undefined, null],
    ['dry-run', ['42'], null],
    ['apply', undefined, null],
    ['apply', ['42', '18446744073709551615'], '42,18446744073709551615'],
    ['apply', [], ''],
  ] as const)('submits a CSRF-protected multipart request in %s mode for selection %j', async (mode, selectedIds, expected) => {
    const report: WordPressImportReport = {
      mode,
      input: 'wxr',
      source: 'old-site',
      counts: {},
      skippedTypes: {},
      items: [],
      media: [],
      taxonomy: { terms: [], assignments: [] },
      authors: [],
    }
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(Response.json({ token: 'csrf-token' }))
      .mockResolvedValueOnce(Response.json(report))
    vi.stubGlobal('fetch', fetchMock)
    const file = new File(['<rss />'], 'wordpress.xml', { type: 'application/xml' })
    const { signal } = new AbortController()

    await expect(importWordPress(file, 'old-site', mode, signal, selectedIds)).resolves.toEqual(report)

    const [path, init] = fetchMock.mock.calls[1]
    const headers = new Headers(init?.headers)
    const body = init?.body as FormData
    expect(path).toBe('/api/v1/admin/imports/wordpress')
    expect(init?.method).toBe('POST')
    expect(init?.signal).toBe(signal)
    expect(headers.get('X-CSRF-Token')).toBe('csrf-token')
    expect(headers.has('Content-Type')).toBe(false)
    expect(body.get('file')).toBe(file)
    expect(body.get('source')).toBe('old-site')
    expect(body.get('mode')).toBe(mode)
    expect(body.get('selectedIds')).toBe(expected)
  })

  it('exposes a server validation error', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(Response.json({ token: 'csrf-token' }))
      .mockResolvedValueOnce(Response.json({ error: 'Invalid WXR file.' }, { status: 422 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(importWordPress(new File([], 'invalid.xml'), 'old-site', 'dry-run', new AbortController().signal))
      .rejects.toThrow('Invalid WXR file.')
  })
})

describe('WordPress import history', () => {
  it('reads the requested history page and run without a CSRF request', async () => {
    const collection: WordPressImportRunCollection = {
      items: [{ id: '0199b348-1d8f-7e2f-8672-9b8356792e42', source: 'old-site', scope: 'selection', completedAt: '2026-11-20T08:00:00Z', counts: { imported: 1 } }],
      page: 2,
      hasMore: true,
    }
    const report: WordPressImportReport = {
      mode: 'apply',
      input: 'wxr',
      source: 'old-site',
      scope: 'selection',
      counts: { imported: 1 },
      skippedTypes: {},
      items: [],
      media: [],
      taxonomy: { terms: [], assignments: [] },
      authors: [],
    }
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(Response.json(collection))
      .mockResolvedValueOnce(Response.json(report))
    vi.stubGlobal('fetch', fetchMock)
    const { signal } = new AbortController()
    const run = collection.items[0]

    await expect(listWordPressImportRuns(2, signal)).resolves.toEqual(collection)
    await expect(getWordPressImportRun(run.id, signal)).resolves.toEqual(report)
    expect(fetchMock.mock.calls.map(([url]) => url)).toEqual([
      '/api/v1/admin/imports/wordpress/runs?page=2',
      `/api/v1/admin/imports/wordpress/runs/${run.id}`,
    ])
    for (const [, init] of fetchMock.mock.calls) {
      expect(init?.signal).toBe(signal)
      expect(init?.credentials).toBe('same-origin')
      expect(new Headers(init?.headers).has('X-CSRF-Token')).toBe(false)
    }
  })

  it('exposes a missing run error', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(Response.json({ error: 'Import run not found.' }, { status: 404 })))

    await expect(getWordPressImportRun('missing', new AbortController().signal)).rejects.toThrow('Import run not found.')
  })
})
