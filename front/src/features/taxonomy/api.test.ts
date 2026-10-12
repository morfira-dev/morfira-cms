import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfToken } from '../../api/http'
import { createTaxonomy, deleteTerm, putEntryTerms, updateTaxonomy } from './api'

afterEach(()=>{vi.unstubAllGlobals();resetCsrfToken()})

describe('taxonomy API',()=>{
  it('creates hierarchical taxonomies through the typed contract',async()=>{
    const result={id:'11111111-1111-4111-8111-111111111111',name:'Topics',slug:'topics',hierarchical:true,createdAt:'2026-10-12T12:00:00Z',terms:[]}
    const fetchMock=vi.fn<typeof fetch>().mockResolvedValueOnce(new Response(JSON.stringify({token:'csrf'}),{status:200})).mockResolvedValueOnce(new Response(JSON.stringify(result),{status:201}));vi.stubGlobal('fetch',fetchMock)
    await expect(createTaxonomy('Topics','topics',true)).resolves.toEqual(result)
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual({name:'Topics',slug:'topics',hierarchical:true})
  })
  it('replaces the complete entry assignment',async()=>{
    const fetchMock=vi.fn<typeof fetch>().mockResolvedValueOnce(new Response(JSON.stringify({token:'csrf'}),{status:200})).mockResolvedValueOnce(new Response(JSON.stringify({items:[]}),{status:200}));vi.stubGlobal('fetch',fetchMock)
    await expect(putEntryTerms('entry-id',['term-id'])).resolves.toEqual([])
    expect(JSON.parse(String(fetchMock.mock.calls[1][1]?.body))).toEqual({termIds:['term-id']})
  })
  it('updates a taxonomy through the generated contract',async()=>{
    const result={id:'11111111-1111-4111-8111-111111111111',name:'Subjects',slug:'subjects',hierarchical:false,createdAt:'2026-10-12T12:00:00Z',terms:[]}
    const fetchMock=vi.fn<typeof fetch>().mockResolvedValueOnce(new Response(JSON.stringify({token:'csrf'}),{status:200})).mockResolvedValueOnce(new Response(JSON.stringify(result),{status:200}));vi.stubGlobal('fetch',fetchMock)
    await expect(updateTaxonomy(result.id,'Subjects','subjects',false)).resolves.toEqual(result)
    expect(fetchMock.mock.calls[1][0]).toBe(`/api/v1/admin/taxonomies/${result.id}`)
    expect(fetchMock.mock.calls[1][1]?.method).toBe('PUT')
  })
  it('deletes a term with an authenticated request',async()=>{
    const result={id:'11111111-1111-4111-8111-111111111111',name:'Topics',slug:'topics',hierarchical:true,createdAt:'2026-10-12T12:00:00Z',terms:[]}
    const fetchMock=vi.fn<typeof fetch>().mockResolvedValueOnce(new Response(JSON.stringify({token:'csrf'}),{status:200})).mockResolvedValueOnce(new Response(JSON.stringify(result),{status:200}));vi.stubGlobal('fetch',fetchMock)
    await expect(deleteTerm(result.id,'term-id')).resolves.toEqual(result)
    expect(fetchMock.mock.calls[1][0]).toBe(`/api/v1/admin/taxonomies/${result.id}/terms/term-id`)
    expect(fetchMock.mock.calls[1][1]?.method).toBe('DELETE')
  })
})
