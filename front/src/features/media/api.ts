import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type MediaVariant = components['schemas']['MediaVariant']
export type MediaAsset = components['schemas']['MediaAsset']
type MediaCollection = components['schemas']['MediaAssetCollection']

export async function getMedia(signal?: AbortSignal): Promise<MediaAsset[]> {
  const response = await apiFetch('/api/v1/admin/media', { signal })

  if (!response.ok) {
    throw new Error(await readApiError(response))
  }

  return ((await response.json()) as MediaCollection).items
}

export async function uploadMedia(file: File, alt: string): Promise<MediaAsset> {
  const body = new FormData()
  body.set('file', file)
  body.set('alt', alt)

  const response = await apiFetch('/api/v1/admin/media', {
    method: 'POST',
    body,
  })

  if (!response.ok) {
    throw new Error(await readApiError(response))
  }

  return (await response.json()) as MediaAsset
}

export async function updateMediaMetadata(
  id: string,
  metadata: Pick<MediaAsset, 'defaultAlt' | 'collection' | 'tags'>,
): Promise<MediaAsset> {
  const response = await apiFetch(`/api/v1/admin/media/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(metadata),
  })

  if (!response.ok) throw new Error(await readApiError(response))

  return (await response.json()) as MediaAsset
}

export async function deleteMedia(id: string): Promise<void> {
  const response = await apiFetch(`/api/v1/admin/media/${id}`, { method: 'DELETE' })

  if (!response.ok) throw new Error(await readApiError(response))
}
