import { type FormEvent, type ReactNode, useEffect, useState } from 'react'
import { Alert, Button, Card, CardContent, CircularProgress, Stack, TextField, Typography } from '../../ui'
import { getCurrentUser, getSetupStatus, login, logout, setupOwner, type AuthenticatedUser } from './api'

interface AuthGateProps {
  children: (user: AuthenticatedUser, signOut: () => Promise<void>, sessionEnded: () => void) => ReactNode
}

type Mode = 'loading' | 'setup' | 'login' | 'authenticated'

export function AuthGate({ children }: AuthGateProps) {
  const [mode, setMode] = useState<Mode>('loading')
  const [user, setUser] = useState<AuthenticatedUser | null>(null)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    Promise.all([getSetupStatus(), getCurrentUser()])
      .then(([setupRequired, currentUser]) => {
        if (setupRequired) setMode('setup')
        else if (currentUser) {
          setUser(currentUser)
          setMode('authenticated')
        } else setMode('login')
      })
      .catch((reason: unknown) => {
        setError(reason instanceof Error ? reason.message : 'Could not initialize Admin Studio.')
        setMode('login')
      })
  }, [])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      if (mode === 'setup') {
        if (password !== confirmPassword) throw new Error('Passwords do not match.')
        await setupOwner(email, password)
      }

      const authenticated = await login(email, password)
      setUser(authenticated)
      setPassword('')
      setConfirmPassword('')
      setMode('authenticated')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Authentication failed.')
    } finally {
      setSubmitting(false)
    }
  }

  async function signOut() {
    await logout()
    setUser(null)
    setMode('login')
  }

  if (mode === 'authenticated' && user) return children(user, signOut, () => {
    setUser(null)
    setMode('login')
  })

  return (
    <Stack sx={{ minHeight: '100vh', alignItems: 'center', justifyContent: 'center', bgcolor: 'background.default', p: 3 }}>
      <Card variant="outlined" sx={{ width: '100%', maxWidth: 460 }}>
        <CardContent>
          {mode === 'loading' ? (
            <Stack spacing={2} sx={{ py: 6, alignItems: 'center' }}>
              <CircularProgress size={30} />
              <Typography color="text.secondary">Initializing secure session…</Typography>
            </Stack>
          ) : (
            <Stack component="form" spacing={2.5} onSubmit={submit}>
              <div>
                <Typography variant="h4">{mode === 'setup' ? 'Create the CMS owner' : 'Welcome back'}</Typography>
                <Typography color="text.secondary" sx={{ mt: 1 }}>
                  {mode === 'setup'
                    ? 'This first account controls installation and editorial access.'
                    : 'Sign in to manage drafts, previews and publishing.'}
                </Typography>
              </div>

              {error && <Alert severity="error">{error}</Alert>}

              <TextField label="Email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
              <TextField
                label="Password"
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
                helperText={mode === 'setup' ? 'At least 12 characters' : undefined}
                slotProps={{ htmlInput: { minLength: mode === 'setup' ? 12 : undefined } }}
              />
              {mode === 'setup' && (
                <TextField
                  label="Confirm password"
                  type="password"
                  value={confirmPassword}
                  onChange={(event) => setConfirmPassword(event.target.value)}
                  required
                />
              )}
              <Button type="submit" variant="contained" size="large" disabled={submitting}>
                {submitting ? 'Please wait…' : mode === 'setup' ? 'Create owner' : 'Sign in'}
              </Button>
            </Stack>
          )}
        </CardContent>
      </Card>
    </Stack>
  )
}
