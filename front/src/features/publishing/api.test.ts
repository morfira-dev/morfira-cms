import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { getPublicationSchedule, putPublicationSchedule } from './api'

afterEach(() => {
  vi.unstubAllGlobals()
  resetCsrfToken()
})

describe('publication schedule API', () => {
  it('maps a missing schedule to null', async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValueOnce(new Response('', { status: 404 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(getPublicationSchedule('page', 'page-id')).resolves.toBeNull()
    expect(fetchMock.mock.calls[0][0]).toBe('/api/v1/admin/pages/page-id/schedule')
  })

  it('sends an exact entry version and UTC timestamp with CSRF protection', async () => {
    const schedule = {
      id: '11111111-1111-4111-8111-111111111111',
      targetType: 'entry' as const,
      targetId: '22222222-2222-4222-8222-222222222222',
      sourceVersion: 3,
      scheduledAt: '2026-10-13T09:00:00.000Z',
      status: 'pending' as const,
      failureReason: null,
      createdAt: '2026-10-12T09:00:00.000Z',
      updatedAt: '2026-10-12T09:00:00.000Z',
    }
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(schedule), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(putPublicationSchedule('entry', schedule.targetId, 3, schedule.scheduledAt)).resolves.toEqual(schedule)
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual({ sourceVersion: 3, scheduledAt: schedule.scheduledAt })
  })
})
