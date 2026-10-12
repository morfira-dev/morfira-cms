import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type EntryValue = components['schemas']['EntryValue']
export type EntryValues = components['schemas']['EntryValues']
export type Entry = components['schemas']['Entry']
export type EntryCreate = components['schemas']['CreateEntryRequest']
export type EntryUpdate = components['schemas']['UpdateEntryRequest']
export type PublishedEntry = components['schemas']['PublishedEntry']

type EntryCollection = components['schemas']['EntryCollection']

export async function getEntries(contentTypeId: string, signal?: AbortSignal): Promise<Entry[]> {
  const query = new URLSearchParams({ contentTypeId })
  const response = await apiFetch(`/api/v1/admin/entries?${query}`, { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return ((await response.json()) as EntryCollection).items
}

export async function createEntry(payload: EntryCreate): Promise<Entry> {
  return sendJson('/api/v1/admin/entries', 'POST', payload)
}

export async function getEntry(id: string, signal?: AbortSignal): Promise<Entry> {
  const response = await apiFetch(`/api/v1/admin/entries/${id}`, { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as Entry
}

export async function updateEntry(id: string, payload: EntryUpdate): Promise<Entry> {
  return sendJson(`/api/v1/admin/entries/${id}`, 'PUT', payload)
}

export async function publishEntry(id: string, version: number): Promise<PublishedEntry> {
  return sendJson(`/api/v1/admin/entries/${id}/publish`, 'POST', { version })
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
