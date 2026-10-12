import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

type UserCollection = components['schemas']['UserCollection']
export type CmsUser = components['schemas']['UserSummary']
export type UserRole = components['schemas']['UserRole']
export type CreateUserRequest = components['schemas']['CreateUserRequest']

export async function getUsers(signal?: AbortSignal): Promise<CmsUser[]> {
  const response = await apiFetch('/api/v1/admin/users', { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return ((await response.json()) as UserCollection).items
}

export async function createUser(request: CreateUserRequest): Promise<CmsUser> {
  const response = await apiFetch('/api/v1/admin/users', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(request),
  })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as CmsUser
}

export async function updateUserRole(id: string, role: UserRole): Promise<CmsUser> {
  const response = await apiFetch(`/api/v1/admin/users/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ role }),
  })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as CmsUser
}

export async function deleteUser(id: string): Promise<void> {
  const response = await apiFetch(`/api/v1/admin/users/${id}`, { method: 'DELETE' })
  if (!response.ok) throw new Error(await readApiError(response))
}
