import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type Taxonomy = components['schemas']['Taxonomy']
export type TaxonomyTerm = components['schemas']['TaxonomyTerm']

export async function getTaxonomies(signal?:AbortSignal):Promise<Taxonomy[]>{const r=await apiFetch('/api/v1/admin/taxonomies',{signal});if(!r.ok)throw new Error(await readApiError(r));return ((await r.json()) as components['schemas']['TaxonomyCollection']).items}
export async function createTaxonomy(name:string,slug:string,hierarchical:boolean):Promise<Taxonomy>{return send('/api/v1/admin/taxonomies','POST',{name,slug,hierarchical})}
export async function updateTaxonomy(id:string,name:string,slug:string,hierarchical:boolean):Promise<Taxonomy>{return send(`/api/v1/admin/taxonomies/${id}`,'PUT',{name,slug,hierarchical})}
export async function deleteTaxonomy(id:string):Promise<void>{const r=await apiFetch(`/api/v1/admin/taxonomies/${id}`,{method:'DELETE'});if(!r.ok)throw new Error(await readApiError(r))}
export async function createTerm(taxonomyId:string,name:string,slug:string,parentId:string|null):Promise<Taxonomy>{return send(`/api/v1/admin/taxonomies/${taxonomyId}/terms`,'POST',{name,slug,parentId})}
export async function updateTerm(taxonomyId:string,termId:string,name:string,slug:string,parentId:string|null):Promise<Taxonomy>{return send(`/api/v1/admin/taxonomies/${taxonomyId}/terms/${termId}`,'PUT',{name,slug,parentId})}
export async function deleteTerm(taxonomyId:string,termId:string):Promise<Taxonomy>{const r=await apiFetch(`/api/v1/admin/taxonomies/${taxonomyId}/terms/${termId}`,{method:'DELETE'});if(!r.ok)throw new Error(await readApiError(r));return (await r.json()) as Taxonomy}
export async function getEntryTerms(entryId:string,signal?:AbortSignal):Promise<TaxonomyTerm[]>{const r=await apiFetch(`/api/v1/admin/entries/${entryId}/terms`,{signal});if(!r.ok)throw new Error(await readApiError(r));return ((await r.json()) as components['schemas']['TaxonomyTermCollection']).items}
export async function putEntryTerms(entryId:string,termIds:string[]):Promise<TaxonomyTerm[]>{const result=await send<components['schemas']['TaxonomyTermCollection']>(`/api/v1/admin/entries/${entryId}/terms`,'PUT',{termIds});return result.items}
async function send<T>(url:string,method:'POST'|'PUT',body:unknown):Promise<T>{const r=await apiFetch(url,{method,headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});if(!r.ok)throw new Error(await readApiError(r));return (await r.json()) as T}
