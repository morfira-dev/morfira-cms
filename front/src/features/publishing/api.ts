import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type PublicationSchedule = components['schemas']['PublicationSchedule']
export type PublicationTargetType = PublicationSchedule['targetType']

export async function getPublicationSchedule(target: PublicationTargetType, id: string, signal?: AbortSignal): Promise<PublicationSchedule | null> {
  const response = await apiFetch(scheduleUrl(target, id), { signal })
  if (response.status === 404) return null
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as PublicationSchedule
}

export async function putPublicationSchedule(target: PublicationTargetType, id: string, sourceVersion: number, scheduledAt: string): Promise<PublicationSchedule> {
  const response = await apiFetch(scheduleUrl(target, id), {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ sourceVersion, scheduledAt }),
  })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as PublicationSchedule
}

export async function cancelPublicationSchedule(target: PublicationTargetType, id: string): Promise<PublicationSchedule> {
  const response = await apiFetch(scheduleUrl(target, id), { method: 'DELETE' })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as PublicationSchedule
}

function scheduleUrl(target: PublicationTargetType, id: string): string {
  return `/api/v1/admin/${target === 'page' ? 'pages' : 'entries'}/${id}/schedule`
}
