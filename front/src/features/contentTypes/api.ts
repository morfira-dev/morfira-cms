import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type ContentField = components['schemas']['ContentField']
export type ContentFieldType = ContentField['type']
export type ContentType = components['schemas']['ContentType']
export type ContentTypeCreate = components['schemas']['CreateContentTypeRequest']
export type ContentTypeUpdate = components['schemas']['UpdateContentTypeRequest']

type ContentTypeCollection = components['schemas']['ContentTypeCollection']

export async function getContentTypes(signal?: AbortSignal): Promise<ContentType[]> {
  const response = await apiFetch('/api/v1/admin/content-types', { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return ((await response.json()) as ContentTypeCollection).items
}

export async function createContentType(payload: ContentTypeCreate): Promise<ContentType> {
  return sendJson('/api/v1/admin/content-types', 'POST', payload)
}

export async function getContentType(id: string, signal?: AbortSignal): Promise<ContentType> {
  const response = await apiFetch(`/api/v1/admin/content-types/${id}`, { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as ContentType
}

export async function updateContentType(id: string, payload: ContentTypeUpdate): Promise<ContentType> {
  return sendJson(`/api/v1/admin/content-types/${id}`, 'PUT', payload)
}

async function sendJson<T>(url: string, method: 'POST' | 'PUT', payload: unknown): Promise<T> {
  const response = await apiFetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as T
}
