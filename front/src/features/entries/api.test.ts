import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { createEntry, type EntryCreate } from './api'

const payload: EntryCreate = { contentTypeId: '11111111-1111-4111-8111-111111111111', title: 'First', slug: 'first', values: { featured: true, score: 5 } }

afterEach(() => { vi.unstubAllGlobals(); resetCsrfToken() })

describe('createEntry', () => {
  it('sends the selected content type and typed values', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ id: 'entry', ...payload, version: 1 }), { status: 201 }))
    vi.stubGlobal('fetch', fetchMock)
    await createEntry(payload)
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual(payload)
  })
})
