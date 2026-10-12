import { lazy, Suspense } from 'react'
import { AuthGate } from './features/auth/AuthGate'
import { adminRoutes, useAdminRoute } from './routing/adminRoutes'
import { AdminShell, CircularProgress, Stack } from './ui'
import { useAdminLocale } from './i18n/AdminLocaleProvider'

const ContentWorkspace = lazy(async () => {
  const module = await import('./features/content/ContentWorkspace')
  return { default: module.ContentWorkspace }
})

export function App() {
  const { route, navigate } = useAdminRoute()
  const { t } = useAdminLocale()

  return (
    <AuthGate>
      {(user, signOut, sessionEnded, changeAdminLocale) => {
        const isOwner = user.roles.includes('ROLE_OWNER')
        const navigation = adminRoutes
          .filter((item) => !item.ownerOnly || isOwner)
          .map((item) => ({ ...item, label: t(item.label), active: item.id === route.id }))

        return (
          <AdminShell navigation={navigation} userEmail={user.email} onNavigate={navigate} onLogout={() => void signOut()} onLocaleChange={changeAdminLocale}>
            <Suspense fallback={(
              <Stack sx={{ minHeight: 320, alignItems: 'center', justifyContent: 'center' }}>
                <CircularProgress size={30} />
              </Stack>
            )}>
              <ContentWorkspace route={route.id} isOwner={isOwner} currentUserId={user.id} currentAdminLocale={user.adminLocale} onAdminLocaleChange={changeAdminLocale} onNavigate={navigate} onSessionEnded={sessionEnded} />
            </Suspense>
          </AdminShell>
        )
      }}
    </AuthGate>
  )
}
