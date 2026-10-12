import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

type PageCollection = components['schemas']['PageCollection']
export type PageBlock = components['schemas']['PageBlock']
export type PageSummary = components['schemas']['Page']
export type PageUpdate = components['schemas']['UpdatePageRequest']
export type PublishedPage = components['schemas']['PublishedPage']
export type PublishedRedirect = components['schemas']['PublishedRedirect']

export async function getPages(signal?: AbortSignal): Promise<PageSummary[]> {
  const response = await apiFetch('/api/v1/admin/pages', { signal })

  if (!response.ok) {
    throw new Error(await readApiError(response))
  }

  const body = (await response.json()) as PageCollection
  return body.items
}

export async function createPage(title: string, slug: string): Promise<PageSummary> {
  const response = await apiFetch('/api/v1/admin/pages', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, slug }),
  })

  if (!response.ok) {
    throw new Error(await readApiError(response))
  }

  return (await response.json()) as PageSummary
}

export async function updatePage(id: string, revision: PageUpdate): Promise<PageSummary> {
  const response = await apiFetch(`/api/v1/admin/pages/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(revision),
  })

  if (!response.ok) {
    throw new Error(await readApiError(response))
  }

  return (await response.json()) as PageSummary
}

export async function deletePage(id: string): Promise<void> {
  const response = await apiFetch(`/api/v1/admin/pages/${id}`, { method: 'DELETE' })
  if (!response.ok) throw new Error(await readApiError(response))
}

export async function publishPage(id: string, version: number): Promise<PublishedPage> {
  const response = await apiFetch(`/api/v1/admin/pages/${id}/publish`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ version }),
  })

  if (!response.ok) {
    throw new Error(await readApiError(response))
  }

  return (await response.json()) as PublishedPage
}

export async function unpublishPage(id: string): Promise<void> {
  const response = await apiFetch(`/api/v1/admin/pages/${id}/publish`, { method: 'DELETE' })

  if (!response.ok) {
    throw new Error(await readApiError(response))
  }
}

export async function getPageRedirects(id: string): Promise<PublishedRedirect[]> {
  const response = await apiFetch(`/api/v1/admin/pages/${id}/redirects`)

  if (!response.ok) {
    throw new Error(await readApiError(response))
  }

  const body = (await response.json()) as components['schemas']['PublishedRedirectCollection']
  return body.items
}
