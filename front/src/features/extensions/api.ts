import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type Extension = components['schemas']['Extension']

export async function getExtensions(signal?: AbortSignal): Promise<{ apiVersion: number; items: Extension[] }> {
  const response = await apiFetch('/api/v1/admin/extensions', { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as components['schemas']['ExtensionCollection']
}
