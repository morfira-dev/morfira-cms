import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { getWordPressImportRun, importWordPress, importWordPressRest, listWordPressImportRuns, type WordPressImportReport, type WordPressImportRunCollection } from './api'

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

describe('importWordPressRest', () => {
  it.each([
    ['dry-run', { selectedIds: ['42'] }, {}],
    ['apply', {}, {}],
    ['apply', { selectedIds: ['42', '18446744073709551615'] }, { selectedIds: '42,18446744073709551615' }],
    ['apply', { selectedIds: [] }, { selectedIds: '' }],
    ['dry-run', { downloadMedia: false }, {}],
    ['dry-run', { downloadMedia: true, selectedIds: ['42'] }, { downloadMedia: true }],
    ['apply', { downloadMedia: true, selectedIds: ['42'] }, { downloadMedia: true, selectedIds: '42' }],
    ['dry-run', { downloadMedia: false, allowedMediaHosts: ['cdn.example'] }, {}],
    ['dry-run', { downloadMedia: true, allowedMediaHosts: [] }, { downloadMedia: true }],
    ['dry-run', { downloadMedia: true, allowedMediaHosts: ['cdn.example'] }, { downloadMedia: true, allowedMediaHosts: ['cdn.example'] }],
    ['apply', { downloadMedia: true, allowedMediaHosts: ['cdn.example'], selectedIds: ['42'] }, {
      downloadMedia: true, allowedMediaHosts: ['cdn.example'], selectedIds: '42',
    }],
  ] as const)('submits a CSRF-protected JSON request in %s mode with options %j', async (mode, options, optionalFields) => {
    const report: WordPressImportReport = {
      mode,
      input: 'rest',
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
    const apiUrl = 'https://old-site.example/blog/wp-json/wp/v2'
    const { signal } = new AbortController()

    await expect(importWordPressRest(apiUrl, 'old-site', mode, signal, options)).resolves.toEqual(report)

    const [path, init] = fetchMock.mock.calls[1]
    const headers = new Headers(init?.headers)
    expect(path).toBe('/api/v1/admin/imports/wordpress/rest')
    expect(init?.method).toBe('POST')
    expect(init?.signal).toBe(signal)
    expect(headers.get('X-CSRF-Token')).toBe('csrf-token')
    expect(headers.get('Content-Type')).toBe('application/json')
    expect(JSON.parse(String(init?.body))).toEqual({ apiUrl, source: 'old-site', mode, ...optionalFields })
  })
})

describe('WordPress import history', () => {
  it('reads the requested history page and run without a CSRF request', async () => {
    const collection: WordPressImportRunCollection = {
      items: [{ id: '0199b348-1d8f-7e2f-8672-9b8356792e42', source: 'old-site', input: 'wxr', scope: 'selection', channel: 'studio', completedAt: '2026-11-20T08:00:00Z', counts: { imported: 1 } }],
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

    await expect(listWordPressImportRuns({ page: 2 }, signal)).resolves.toEqual(collection)
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

  it('sends only the applied history filters with the page', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockImplementation(async () => Response.json({ items: [], page: 1, hasMore: false }))
    vi.stubGlobal('fetch', fetchMock)
    const { signal } = new AbortController()

    await listWordPressImportRuns({ page: 1, source: 'old-site', input: 'rest', scope: 'all', channel: 'cli' }, signal)
    await listWordPressImportRuns({ page: 1, scope: 'all' }, signal)

    expect(fetchMock.mock.calls.map(([url]) => url)).toEqual([
      '/api/v1/admin/imports/wordpress/runs?page=1&source=old-site&input=rest&scope=all&channel=cli',
      '/api/v1/admin/imports/wordpress/runs?page=1&scope=all',
    ])
  })

  it('exposes a missing run error', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockResolvedValue(Response.json({ error: 'Import run not found.' }, { status: 404 })))

    await expect(getWordPressImportRun('missing', new AbortController().signal)).rejects.toThrow('Import run not found.')
  })
})
