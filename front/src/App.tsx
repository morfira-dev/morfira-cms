import { lazy, Suspense } from 'react'
import { AuthGate } from './features/auth/AuthGate'
import { adminRoutes, useAdminRoute } from './routing/adminRoutes'
import { AdminShell, CircularProgress, Stack } from './ui'

const ContentWorkspace = lazy(async () => {
  const module = await import('./features/content/ContentWorkspace')
  return { default: module.ContentWorkspace }
})

export function App() {
  const { route, navigate } = useAdminRoute()

  return (
    <AuthGate>
      {(user, signOut, sessionEnded) => {
        const isOwner = user.roles.includes('ROLE_OWNER')
        const navigation = adminRoutes
          .filter((item) => !item.ownerOnly || isOwner)
          .map((item) => ({ ...item, active: item.id === route.id }))

        return (
          <AdminShell navigation={navigation} userEmail={user.email} onNavigate={navigate} onLogout={() => void signOut()}>
            <Suspense fallback={(
              <Stack sx={{ minHeight: 320, alignItems: 'center', justifyContent: 'center' }}>
                <CircularProgress size={30} />
              </Stack>
            )}>
              <ContentWorkspace route={route.id} isOwner={isOwner} currentUserId={user.id} onNavigate={navigate} onSessionEnded={sessionEnded} />
            </Suspense>
          </AdminShell>
        )
      }}
    </AuthGate>
  )
}
