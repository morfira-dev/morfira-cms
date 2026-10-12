import { type FormEvent, type ReactNode, useEffect, useState } from 'react'
import { Alert, Button, Card, CardContent, CircularProgress, Stack, TextField, Typography } from '../../ui'
import { getCurrentUser, getSetupStatus, login, logout, setupOwner, updateAdminLocale, type AuthenticatedUser } from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'

interface AuthGateProps {
  children: (user: AuthenticatedUser, signOut: () => Promise<void>, sessionEnded: () => void, changeAdminLocale: (locale: AuthenticatedUser['adminLocale']) => Promise<void>) => ReactNode
}

type Mode = 'loading' | 'setup' | 'login' | 'authenticated'

export function AuthGate({ children }: AuthGateProps) {
  const { errorMessage, locale, setLocale, t } = useAdminLocale()
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
          setLocale(currentUser.adminLocale)
          setMode('authenticated')
        } else setMode('login')
      })
      .catch((reason: unknown) => {
        setError(errorMessage(reason, 'Could not initialize Admin Studio.'))
        setMode('login')
      })
  }, [])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      if (mode === 'setup') {
        if (password !== confirmPassword) throw new Error(t('Passwords do not match.'))
        await setupOwner(email, password)
      }

      const authenticated = await login(email, password)
      setUser(authenticated)
      setLocale(authenticated.adminLocale)
      setPassword('')
      setConfirmPassword('')
      setMode('authenticated')
    } catch (reason) {
      setError(errorMessage(reason, 'Authentication failed.'))
    } finally {
      setSubmitting(false)
    }
  }

  async function signOut() {
    await logout()
    setUser(null)
    setMode('login')
    setLocale('en')
  }

  async function changeAdminLocale(locale: AuthenticatedUser['adminLocale']) {
    await updateAdminLocale(locale)
    setUser((current) => current ? { ...current, adminLocale: locale } : current)
    setLocale(locale)
  }

  if (mode === 'authenticated' && user) return children(user, signOut, () => {
    setUser(null)
    setMode('login')
    setLocale('en')
  }, changeAdminLocale)

  return (
    <Stack sx={{ minHeight: '100vh', alignItems: 'center', justifyContent: 'center', bgcolor: 'background.default', p: 3 }}>
      <Card variant="outlined" sx={{ width: '100%', maxWidth: 460 }}>
        <CardContent>
          {mode === 'loading' ? (
            <Stack spacing={2} sx={{ py: 6, alignItems: 'center' }}>
              <CircularProgress size={30} />
              <Typography color="text.secondary">{t('Initializing secure session…')}</Typography>
            </Stack>
          ) : (
            <Stack component="form" spacing={2.5} onSubmit={submit}>
              <div>
                <Typography variant="h4">{mode === 'setup' ? t('Create the CMS owner') : t('Welcome back')}</Typography>
                <Typography color="text.secondary" sx={{ mt: 1 }}>
                  {mode === 'setup'
                    ? t('This first account controls installation and editorial access.')
                    : t('Sign in to manage drafts, previews and publishing.')}
                </Typography>
              </div>

              {error && <Alert severity="error">{error}</Alert>}

              <TextField label={t('Email')} type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
              <TextField
                label={t('Password')}
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
                helperText={mode === 'setup' ? t('At least 12 characters') : undefined}
                slotProps={{ htmlInput: { minLength: mode === 'setup' ? 12 : undefined } }}
              />
              {mode === 'setup' && (
                <TextField
                  label={t('Confirm password')}
                  type="password"
                  value={confirmPassword}
                  onChange={(event) => setConfirmPassword(event.target.value)}
                  required
                />
              )}
              <Button type="submit" variant="contained" size="large" disabled={submitting}>
                {submitting ? t('Please wait…') : mode === 'setup' ? t('Create owner') : t('Sign in')}
              </Button>
              <Button size="small" onClick={() => setLocale(locale === 'en' ? 'ru' : 'en')}>
                {locale === 'en' ? t('Russian') : t('English')}
              </Button>
            </Stack>
          )}
        </CardContent>
      </Card>
    </Stack>
  )
}
