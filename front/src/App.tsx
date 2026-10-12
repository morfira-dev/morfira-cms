import { lazy, Suspense } from 'react'
import { AuthGate } from './features/auth/AuthGate'
import { AdminShell, CircularProgress, Stack } from './ui'

const ContentWorkspace = lazy(async () => {
  const module = await import('./features/content/ContentWorkspace')
  return { default: module.ContentWorkspace }
})

const navigation = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'content', label: 'Content', active: true },
  { id: 'media', label: 'Media' },
  { id: 'navigation', label: 'Navigation' },
  { id: 'content-types', label: 'Content types' },
  { id: 'entries', label: 'Entries' },
  { id: 'settings', label: 'Settings' },
]

export function App() {
  return (
    <AuthGate>
      {(user, signOut) => (
        <AdminShell navigation={navigation} userEmail={user.email} onLogout={() => void signOut()}>
          <Suspense fallback={(
            <Stack sx={{ minHeight: 320, alignItems: 'center', justifyContent: 'center' }}>
              <CircularProgress size={30} />
            </Stack>
          )}>
            <ContentWorkspace />
          </Suspense>
        </AdminShell>
      )}
    </AuthGate>
  )
}
