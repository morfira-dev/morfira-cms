let csrfToken: string | null = null

async function getCsrfToken(): Promise<string> {
  if (csrfToken) return csrfToken

  const response = await fetch('/api/v1/auth/csrf', { credentials: 'same-origin' })
  if (!response.ok) throw new Error('Could not initialize the secure session.')

  const body = (await response.json()) as { token?: unknown }
  if (typeof body.token !== 'string') throw new Error('The backend returned an invalid CSRF token.')

  csrfToken = body.token
  return csrfToken
}

export function resetCsrfToken() {
  csrfToken = null
}

export async function apiFetch(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
  const method = (init.method ?? 'GET').toUpperCase()
  const headers = new Headers(init.headers)

  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers.set('X-CSRF-Token', await getCsrfToken())
  }

  return fetch(input, {
    ...init,
    headers,
    credentials: 'same-origin',
  })
}

export async function readApiError(response: Response): Promise<string> {
  const fallback = `Request failed with ${response.status}.`

  try {
    const body = (await response.json()) as { error?: unknown }
    return typeof body.error === 'string' ? body.error : fallback
  } catch {
    return fallback
  }
}
