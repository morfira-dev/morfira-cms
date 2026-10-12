import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { createUser, deleteUser, updateUserRole } from './api'

afterEach(() => { vi.unstubAllGlobals(); resetCsrfToken() })

describe('user administration API', () => {
  it('creates users with the protected request contract', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 'user-id', email: 'editor@example.com', role: 'editor', createdAt: '2026-10-12T10:00:00Z' }), { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)

    await createUser({ email: 'editor@example.com', password: 'long-password', role: 'editor' })

    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/admin/users')
    expect(fetchMock.mock.calls[1][1]?.method).toBe('POST')
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual({ email: 'editor@example.com', password: 'long-password', role: 'editor' })
  })

  it('updates roles and deletes users through CSRF-protected requests', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 'user-id', email: 'owner@example.com', role: 'owner', createdAt: '2026-10-12T10:00:00Z' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await updateUserRole('user-id', 'owner')
    await deleteUser('user-id')

    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/admin/users/user-id')
    expect(fetchMock.mock.calls[1][1]?.method).toBe('PUT')
    expect(fetchMock.mock.calls[2][1]?.method).toBe('DELETE')
  })
})
