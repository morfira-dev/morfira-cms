import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { getSiteSettings, updateSiteSettings } from './api'

afterEach(() => { vi.unstubAllGlobals(); resetCsrfToken() })

describe('site settings API', () => {
  it('reads settings without CSRF and updates them with optimistic versioning', async () => {
    const settings = { siteName: 'Morfira CMS', defaultLocale: 'en', timezone: 'UTC', version: 1, updatedAt: '2026-10-12T00:00:00Z' }
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify(settings), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ ...settings, version: 2 }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(getSiteSettings()).resolves.toEqual(settings)
    await updateSiteSettings({ siteName: settings.siteName, defaultLocale: settings.defaultLocale, timezone: settings.timezone, version: 1 })

    expect(fetchMock.mock.calls[2][0]).toBe('/api/v1/admin/settings')
    expect(fetchMock.mock.calls[2][1]?.method).toBe('PUT')
  })
})
