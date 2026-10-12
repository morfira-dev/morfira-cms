import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { changePassword, login, updateAdminLocale } from './api'

afterEach(() => { vi.unstubAllGlobals(); resetCsrfToken() })

describe('changePassword', () => {
  it('sends only current and replacement passwords through a CSRF-protected request', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await changePassword('current-password', 'replacement-password')

    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/auth/password')
    expect(fetchMock.mock.calls[1][1]?.method).toBe('PUT')
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual({ currentPassword: 'current-password', newPassword: 'replacement-password' })
  })
})

describe('login', () => {
  it('refreshes CSRF after the authenticated session identifier changes', async () => {
    const user = { id: '2d1661fc-54a6-4a66-a123-3c8f006ee3db', email: 'owner@morfira.test', roles: ['ROLE_OWNER'], adminLocale: 'en' }
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'anonymous-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(user), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'authenticated-token' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await login(user.email, 'Morfira-beta-2026!')
    await changePassword('Morfira-beta-2026!', 'Morfira-beta-2027!')

    expect(fetchMock.mock.calls[2]?.[0]).toBe('/api/v1/auth/csrf')
    expect((fetchMock.mock.calls[3]?.[1]?.headers as Headers).get('X-CSRF-Token')).toBe('authenticated-token')
  })
})

describe('updateAdminLocale', () => {
  it('stores the current user preference through a CSRF-protected request', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ adminLocale: 'ru' }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await updateAdminLocale('ru')

    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/auth/preferences')
    expect(fetchMock.mock.calls[1][1]?.method).toBe('PUT')
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual({ adminLocale: 'ru' })
  })
})
