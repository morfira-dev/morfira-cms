import type { components } from '../../api/schema'
import { apiFetch, readApiError, resetCsrfToken } from '../../api/http'

export type AuthenticatedUser = components['schemas']['AuthenticatedUser']

export async function getSetupStatus(): Promise<boolean> {
  const response = await apiFetch('/api/v1/setup')
  if (!response.ok) throw new Error(await readApiError(response))
  const body = (await response.json()) as components['schemas']['SetupStatus']
  return body.required
}

export async function setupOwner(email: string, password: string): Promise<void> {
  const response = await apiFetch('/api/v1/setup', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  if (!response.ok) throw new Error(await readApiError(response))
  resetCsrfToken()
}

export async function login(email: string, password: string): Promise<AuthenticatedUser> {
  const response = await apiFetch('/api/v1/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  if (!response.ok) throw new Error(await readApiError(response))
  const user = (await response.json()) as AuthenticatedUser
  resetCsrfToken()

  return user
}

export async function getCurrentUser(): Promise<AuthenticatedUser | null> {
  const response = await apiFetch('/api/v1/auth/me')
  if (response.status === 401) return null
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as AuthenticatedUser
}

export async function logout(): Promise<void> {
  const response = await apiFetch('/api/v1/auth/logout', { method: 'POST' })
  if (!response.ok && response.status !== 204) throw new Error(await readApiError(response))
  resetCsrfToken()
}

export async function changePassword(currentPassword: string, newPassword: string): Promise<void> {
  const response = await apiFetch('/api/v1/auth/password', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ currentPassword, newPassword }),
  })
  if (!response.ok) throw new Error(await readApiError(response))
}

export async function updateAdminLocale(adminLocale: AuthenticatedUser['adminLocale']): Promise<void> {
  const response = await apiFetch('/api/v1/auth/preferences', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ adminLocale }),
  })
  if (!response.ok) throw new Error(await readApiError(response))
}
