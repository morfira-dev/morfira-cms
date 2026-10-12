import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { createPageRedirect, deletePage, deletePageRedirect, getPageRedirects, getPageRevisions, restorePageRevision, unpublishPage, updatePage, type PageSummary, type PageUpdate } from './api'

const revision: PageUpdate = {
  title: 'Landing',
  slug: 'landing',
  blocks: [],
  seo: {
    title: 'Landing SEO',
    description: 'A focused description.',
    noIndex: false,
    openGraphImageAssetId: null,
  },
  version: 1,
}

const page: PageSummary = {
  id: '61ee498b-f19c-43a4-9961-aa32c9da3ec2',
  status: 'draft',
  createdAt: '2026-10-10T12:00:00+00:00',
  updatedAt: '2026-10-10T12:00:00+00:00',
  ...revision,
}

afterEach(() => {
  vi.unstubAllGlobals()
  resetCsrfToken()
})

describe('updatePage', () => {
  it('sends SEO settings in the optimistic revision payload', async () => {
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ ...page, version: 2 }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await updatePage(page.id, revision)

    const [, request] = fetchMock.mock.calls[1]
    expect(JSON.parse(String(request?.body))).toEqual(revision)
  })
})

describe('getPageRedirects', () => {
  it('loads permanent redirect history without requesting a CSRF token', async () => {
    const redirects = [{ sourceSlug: 'old-landing', targetSlug: 'landing' }]
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValueOnce(
      new Response(JSON.stringify({ items: redirects }), { status: 200 }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await expect(getPageRedirects(page.id)).resolves.toEqual(redirects)

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0][0]).toBe(`/api/v1/admin/pages/${page.id}/redirects`)
  })
})

describe('manual page redirects', () => {
  it('creates a permanent redirect for the active page', async () => {
    const redirect = { sourceSlug: 'old-landing', targetSlug: 'landing' }
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(redirect), { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(createPageRedirect(page.id, redirect.sourceSlug)).resolves.toEqual(redirect)

    expect(fetchMock.mock.calls[1][0]).toBe(`/api/v1/admin/pages/${page.id}/redirects`)
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual({ sourceSlug: 'old-landing' })
  })

  it('deletes a permanent redirect by source slug', async () => {
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await deletePageRedirect(page.id, 'old-landing')

    expect(fetchMock.mock.calls[1][0]).toBe(`/api/v1/admin/pages/${page.id}/redirects/old-landing`)
    expect(fetchMock.mock.calls[1][1]?.method).toBe('DELETE')
  })
})

describe('unpublishPage', () => {
  it('uses an authenticated DELETE request', async () => {
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await unpublishPage(page.id)

    expect(fetchMock.mock.calls[1][0]).toBe(`/api/v1/admin/pages/${page.id}/publish`)
    expect(fetchMock.mock.calls[1][1]?.method).toBe('DELETE')
    expect(new Headers(fetchMock.mock.calls[1][1]?.headers).get('X-CSRF-Token')).toBe('csrf-token')
  })
})

describe('deletePage', () => {
  it('uses an authenticated DELETE request for the draft resource', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await deletePage(page.id)

    expect(fetchMock.mock.calls[1][0]).toBe(`/api/v1/admin/pages/${page.id}`)
    expect(fetchMock.mock.calls[1][1]?.method).toBe('DELETE')
  })
})

describe('page revision history', () => {
  it('lists history and restores with the current optimistic version', async () => {
    const history = [{ revision: 1, title: 'Landing', slug: 'landing', blocks: [], seo: revision.seo, createdAt: page.createdAt }]
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ items: history }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ ...page, version: 2 }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(getPageRevisions(page.id)).resolves.toEqual(history)
    await restorePageRevision(page.id, 1, page.version)

    expect(fetchMock.mock.calls[2][0]).toBe(`/api/v1/admin/pages/${page.id}/revisions/1/restore`)
    expect(JSON.parse(String(fetchMock.mock.calls[2][1]?.body))).toEqual({ version: 1 })
  })
})
