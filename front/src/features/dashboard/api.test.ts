import { afterEach, describe, expect, it, vi } from 'vitest'
import { getDashboardCounts } from './api'

afterEach(() => vi.unstubAllGlobals())

describe('getDashboardCounts', () => {
  it('loads all module collections in parallel and returns their totals', async () => {
    const bodies = [
      { items: [{ id: 'page' }, { id: 'page-2' }] },
      { items: [{ id: 'media' }] },
      { items: [{ id: 'type' }, { id: 'type-2' }, { id: 'type-3' }] },
      { items: [] },
    ]
    const fetchMock = vi.fn<typeof fetch>()
    bodies.forEach((body) => fetchMock.mockResolvedValueOnce(new Response(JSON.stringify(body), { status: 200 })))
    vi.stubGlobal('fetch', fetchMock)

    await expect(getDashboardCounts()).resolves.toEqual({ pages: 2, media: 1, contentTypes: 3, taxonomies: 0 })
    expect(fetchMock).toHaveBeenCalledTimes(4)
  })
})
