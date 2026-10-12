import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { updateContentType, type ContentType, type ContentTypeUpdate } from './api'

const payload: ContentTypeUpdate = { name: 'Articles', apiName: 'articles', fields: [], version: 2 }
const response: ContentType = { id: '11111111-1111-4111-8111-111111111111', ...payload, version: 3, createdAt: '2026-10-11T10:00:00Z', updatedAt: '2026-10-11T10:01:00Z' }

afterEach(() => { vi.unstubAllGlobals(); resetCsrfToken() })

describe('updateContentType', () => {
  it('sends ordered fields and optimistic version', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(response), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)
    await updateContentType(response.id, payload)
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual(payload)
  })
})
