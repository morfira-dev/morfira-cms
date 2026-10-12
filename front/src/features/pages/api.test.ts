import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { getPageRedirects, updatePage, type PageSummary, type PageUpdate } from './api'

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
