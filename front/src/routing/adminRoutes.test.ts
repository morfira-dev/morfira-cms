import { describe, expect, it } from 'vitest'
import { resolveAdminRoute } from './adminRoutes'

describe('resolveAdminRoute', () => {
  it('keeps a known admin workspace route', () => {
    expect(resolveAdminRoute('/admin/taxonomies').id).toBe('taxonomies')
  })

  it('preserves the legacy root entry as pages', () => {
    expect(resolveAdminRoute('/').path).toBe('/admin/pages')
  })

  it('recovers unknown paths to the dashboard', () => {
    expect(resolveAdminRoute('/admin/unknown').path).toBe('/admin')
  })
})
