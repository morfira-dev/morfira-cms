import { useCallback, useEffect, useState } from 'react'
import type { MessageKey } from '../i18n/messages'

export type AdminRouteId = 'dashboard' | 'pages' | 'media' | 'navigation' | 'content-types' | 'taxonomies' | 'entries' | 'users' | 'extensions' | 'settings'

export interface AdminRoute {
  id: AdminRouteId
  label: MessageKey
  path: string
  ownerOnly?: boolean
}

export const adminRoutes: AdminRoute[] = [
  { id: 'dashboard', label: 'Dashboard', path: '/admin' },
  { id: 'pages', label: 'Pages', path: '/admin/pages' },
  { id: 'media', label: 'Media', path: '/admin/media' },
  { id: 'navigation', label: 'Navigation', path: '/admin/navigation' },
  { id: 'content-types', label: 'Content types', path: '/admin/content-types' },
  { id: 'taxonomies', label: 'Taxonomies', path: '/admin/taxonomies' },
  { id: 'entries', label: 'Entries', path: '/admin/entries' },
  { id: 'users', label: 'Users', path: '/admin/users', ownerOnly: true },
  { id: 'extensions', label: 'Extensions', path: '/admin/extensions', ownerOnly: true },
  { id: 'settings', label: 'Settings', path: '/admin/settings' },
]

const defaultRoute = adminRoutes[1]
const fallbackRoute = adminRoutes[0]

export function resolveAdminRoute(pathname: string): AdminRoute {
  if (pathname === '/') return defaultRoute
  return adminRoutes.find((route) => route.path === pathname) ?? fallbackRoute
}

export function useAdminRoute() {
  const [route, setRoute] = useState(() => resolveAdminRoute(window.location.pathname))

  useEffect(() => {
    const syncRoute = () => {
      const resolved = resolveAdminRoute(window.location.pathname)
      if (window.location.pathname !== resolved.path) window.history.replaceState({}, '', resolved.path)
      setRoute(resolved)
    }

    syncRoute()
    window.addEventListener('popstate', syncRoute)
    return () => window.removeEventListener('popstate', syncRoute)
  }, [])

  const navigate = useCallback((path: string) => {
    const resolved = resolveAdminRoute(path)
    if (window.location.pathname !== resolved.path) window.history.pushState({}, '', resolved.path)
    setRoute(resolved)
  }, [])

  return { route, navigate }
}
