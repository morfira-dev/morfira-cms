import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { changePassword } from './api'

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
