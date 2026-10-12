import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type SiteSettings = components['schemas']['SiteSettings']
export type UpdateSiteSettings = components['schemas']['UpdateSiteSettingsRequest']

export async function getSiteSettings(signal?: AbortSignal): Promise<SiteSettings> {
  const response = await apiFetch('/api/v1/admin/settings', { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as SiteSettings
}

export async function updateSiteSettings(settings: UpdateSiteSettings): Promise<SiteSettings> {
  const response = await apiFetch('/api/v1/admin/settings', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(settings),
  })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as SiteSettings
}
