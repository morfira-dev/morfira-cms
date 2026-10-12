import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type NavigationItem = components['schemas']['NavigationItem']
export type NavigationMenu = components['schemas']['NavigationMenu']
export type NavigationMenuCreate = components['schemas']['CreateNavigationMenuRequest']
export type NavigationMenuUpdate = components['schemas']['UpdateNavigationMenuRequest']
export type PublishedNavigationMenu = components['schemas']['PublishedNavigationMenu']

type NavigationMenuCollection = components['schemas']['NavigationMenuCollection']

export async function getNavigationMenus(signal?: AbortSignal): Promise<NavigationMenu[]> {
  const response = await apiFetch('/api/v1/admin/navigation', { signal })

  if (!response.ok) throw new Error(await readApiError(response))

  return ((await response.json()) as NavigationMenuCollection).items
}

export async function createNavigationMenu(payload: NavigationMenuCreate): Promise<NavigationMenu> {
  return sendJson('/api/v1/admin/navigation', 'POST', payload)
}

export async function getNavigationMenu(id: string, signal?: AbortSignal): Promise<NavigationMenu> {
  const response = await apiFetch(`/api/v1/admin/navigation/${id}`, { signal })

  if (!response.ok) throw new Error(await readApiError(response))

  return (await response.json()) as NavigationMenu
}

export async function updateNavigationMenu(id: string, payload: NavigationMenuUpdate): Promise<NavigationMenu> {
  return sendJson(`/api/v1/admin/navigation/${id}`, 'PUT', payload)
}

export async function publishNavigationMenu(id: string, version: number): Promise<PublishedNavigationMenu> {
  return sendJson(`/api/v1/admin/navigation/${id}/publish`, 'POST', { version })
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
