import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type Theme = components['schemas']['Theme']
export type ReusableSection = components['schemas']['ReusableSection']
export type ReusableSectionUsage = components['schemas']['ReusableSectionUsage']
export type ReusableSectionUsageCollection = components['schemas']['ReusableSectionUsageCollection']
export type RefreshReusableSectionRequest = components['schemas']['RefreshReusableSectionRequest']
export type RefreshReusableSectionResult = components['schemas']['RefreshReusableSectionResult']

export class ReusableSectionConflict extends Error {}

export async function getThemes(signal?: AbortSignal): Promise<Theme[]> {
  const response = await apiFetch('/api/v1/admin/themes', { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return ((await response.json()) as components['schemas']['ThemeCollection']).items
}

export async function getReusableSections(signal?: AbortSignal): Promise<ReusableSection[]> {
  const response = await apiFetch('/api/v1/admin/sections', { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return ((await response.json()) as components['schemas']['ReusableSectionCollection']).items
}

export async function createReusableSection(name: string, blocks: ReusableSection['blocks'], signal?: AbortSignal): Promise<ReusableSection> {
  const response = await apiFetch('/api/v1/admin/sections', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, blocks }),
    signal,
  })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as ReusableSection
}

export async function updateReusableSection(section: ReusableSection, blocks: ReusableSection['blocks'], signal?: AbortSignal): Promise<ReusableSection> {
  const response = await apiFetch(`/api/v1/admin/sections/${section.id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: section.name, version: section.version, blocks }),
    signal,
  })
  if (response.status === 409 || response.status === 404) {
    throw new ReusableSectionConflict(await readApiError(response))
  }
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as ReusableSection
}

export async function deleteReusableSection(id: string, signal?: AbortSignal): Promise<void> {
  const response = await apiFetch(`/api/v1/admin/sections/${id}`, { method: 'DELETE', signal })
  if (!response.ok) throw new Error(await readApiError(response))
}

export async function getReusableSectionUsage(id: string, limit: number, offset: number, signal?: AbortSignal): Promise<ReusableSectionUsageCollection> {
  const query = new URLSearchParams({ limit: String(limit), offset: String(offset) })
  const response = await apiFetch(`/api/v1/admin/sections/${id}/usage?${query}`, { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as ReusableSectionUsageCollection
}

export async function refreshReusableSection(id: string, request: RefreshReusableSectionRequest, signal?: AbortSignal): Promise<RefreshReusableSectionResult[]> {
  const response = await apiFetch(`/api/v1/admin/sections/${id}/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(request),
    signal,
  })
  if (response.status === 409) throw new ReusableSectionConflict(await readApiError(response))
  if (!response.ok) throw new Error(await readApiError(response))
  return ((await response.json()) as components['schemas']['RefreshReusableSectionReport']).items
}
