import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { updateNavigationMenu, type NavigationMenu, type NavigationMenuUpdate } from './api'

const payload: NavigationMenuUpdate = {
  name: 'Main menu',
  code: 'main',
  items: [{
    id: '0b04d550-a534-48a5-9ee8-8b4815bce421',
    label: 'Home',
    href: '/',
    openInNewTab: false,
    children: [],
  }],
  version: 3,
}

const menu: NavigationMenu = {
  id: 'ca30df4a-6caf-4ce6-87ff-06eece17e827',
  ...payload,
  version: 4,
  createdAt: '2026-10-11T10:00:00+00:00',
  updatedAt: '2026-10-11T10:05:00+00:00',
}

afterEach(() => {
  vi.unstubAllGlobals()
  resetCsrfToken()
})

describe('updateNavigationMenu', () => {
  it('sends the complete tree and optimistic-lock version', async () => {
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(menu), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(updateNavigationMenu(menu.id, payload)).resolves.toEqual(menu)

    const [url, request] = fetchMock.mock.calls[1]
    expect(url).toBe(`/api/v1/admin/navigation/${menu.id}`)
    expect(request?.method).toBe('PUT')
    expect(JSON.parse(String(request?.body))).toEqual(payload)
  })
})
