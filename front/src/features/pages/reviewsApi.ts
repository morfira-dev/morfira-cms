import type { components } from '../../api/schema'
import { apiFetch, readApiError } from '../../api/http'

export type PageReview = components['schemas']['PageReview']
export type DecidePageReview = components['schemas']['DecidePageReviewRequest']

export async function getPageReviews(
  filters: { status?: PageReview['status']; pageId?: string; limit?: number },
  signal?: AbortSignal,
): Promise<PageReview[]> {
  const query = new URLSearchParams()
  if (filters.status) query.set('status', filters.status)
  if (filters.pageId) query.set('pageId', filters.pageId)
  if (filters.limit !== undefined) query.set('limit', String(filters.limit))
  const response = await apiFetch(`/api/v1/admin/page-reviews?${query}`, { signal })
  if (!response.ok) throw new Error(await readApiError(response))
  return ((await response.json()) as components['schemas']['PageReviewCollection']).items
}

export async function submitPageReview(pageId: string, revision: number, signal?: AbortSignal): Promise<PageReview> {
  const payload: components['schemas']['CreatePageReviewRequest'] = { revision }
  const response = await apiFetch(`/api/v1/admin/pages/${pageId}/reviews`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    signal,
  })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as PageReview
}

export async function decidePageReview(reviewId: string, payload: DecidePageReview, signal?: AbortSignal): Promise<PageReview> {
  const response = await apiFetch(`/api/v1/admin/page-reviews/${reviewId}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    signal,
  })
  if (!response.ok) throw new Error(await readApiError(response))
  return (await response.json()) as PageReview
}
