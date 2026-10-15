import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { createEntry, deleteEntry, getEntryReferences, getEntryRevisions, restoreEntryRevision, type EntryCreate, unpublishEntry } from './api'

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

describe('unpublishEntry', () => {
  it('uses an authenticated DELETE request', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await unpublishEntry('entry-id')

    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/admin/entries/entry-id/publish')
    expect(fetchMock.mock.calls[1][1]?.method).toBe('DELETE')
    expect(new Headers(fetchMock.mock.calls[1][1]?.headers).get('X-CSRF-Token')).toBe('csrf')
  })
})

describe('deleteEntry', () => {
  it('uses an authenticated DELETE request for the draft resource', async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: 'csrf' }), { status: 200 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await deleteEntry('entry-id')

    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/admin/entries/entry-id')
    expect(fetchMock.mock.calls[1][1]?.method).toBe('DELETE')
  })
})

describe('entry references and revisions', () => {
  it('reads incoming references and revision history', async () => {
    const fetchMock=vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({items:[{id:'source',contentTypeId:'type',title:'Source',fieldKey:'related'}]}),{status:200}))
      .mockResolvedValueOnce(new Response(JSON.stringify({items:[{revision:1,title:'First',slug:'first',values:{},createdAt:'2026-10-16T00:00:00Z'}]}),{status:200}))
    vi.stubGlobal('fetch',fetchMock)
    await expect(getEntryReferences('target')).resolves.toHaveLength(1)
    await expect(getEntryRevisions('target')).resolves.toHaveLength(1)
    expect(fetchMock.mock.calls[0][0]).toBe('/api/v1/admin/entries/target/references')
    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/admin/entries/target/revisions')
  })

  it('restores a revision with the optimistic version', async () => {
    const fetchMock=vi.fn<typeof fetch>()
      .mockResolvedValueOnce(new Response(JSON.stringify({token:'csrf'}),{status:200}))
      .mockResolvedValueOnce(new Response(JSON.stringify({id:'entry',contentTypeId:'type',title:'First',slug:'first',values:{},version:3,createdAt:'',updatedAt:''}),{status:200}))
    vi.stubGlobal('fetch',fetchMock)
    await restoreEntryRevision('entry',1,2)
    expect(fetchMock.mock.calls[1][0]).toBe('/api/v1/admin/entries/entry/revisions/1/restore')
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual({version:2})
  })
})
