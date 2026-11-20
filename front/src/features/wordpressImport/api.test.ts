import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { importWordPress, type WordPressImportReport } from './api'

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
