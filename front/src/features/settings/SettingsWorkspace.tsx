import { type FormEvent, useEffect, useState } from 'react'
import { Alert, Button, Card, CardContent, CircularProgress, MenuItem, Stack, TextField, Typography } from '../../ui'
import { changePassword } from '../auth/api'
import { getSiteSettings, updateSiteSettings, type SiteSettings } from './api'
import { useAdminLocale, type AdminLocale } from '../../i18n/AdminLocaleProvider'

export function SettingsWorkspace({ isOwner, adminLocale, onAdminLocaleChange, onSessionEnded }: { isOwner: boolean; adminLocale: AdminLocale; onAdminLocaleChange: (locale: AdminLocale) => Promise<void>; onSessionEnded: () => void }) {
  const { errorMessage, t } = useAdminLocale()
  const [settings, setSettings] = useState<SiteSettings | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [changingPassword, setChangingPassword] = useState(false)
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    getSiteSettings(controller.signal)
      .then(setSettings)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(errorMessage(reason, 'Could not load settings.'))
      })
      .finally(() => setLoading(false))
    return () => controller.abort()
  }, [errorMessage])

  async function saveSettings(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!settings || !isOwner) return
    setSaving(true); setError(null); setSuccess(null)
    try {
      const saved = await updateSiteSettings({ siteName: settings.siteName, defaultLocale: settings.defaultLocale, timezone: settings.timezone, version: settings.version })
      setSettings(saved); setSuccess(t('Site settings saved.'))
    } catch (reason) { setError(errorMessage(reason, 'Could not save settings.')) }
    finally { setSaving(false) }
  }

  async function submitPassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(null); setSuccess(null)
    if (newPassword !== confirmPassword) { setError(t('New passwords do not match.')); return }
    setChangingPassword(true)
    try {
      await changePassword(currentPassword, newPassword)
      setCurrentPassword(''); setNewPassword(''); setConfirmPassword(''); onSessionEnded()
    } catch (reason) { setError(errorMessage(reason, 'Could not change password.')) }
    finally { setChangingPassword(false) }
  }

  return <Stack spacing={3}>
    <div><Typography component="h1" variant="h1">{t('Settings')}</Typography><Typography color="text.secondary" sx={{ mt: .75 }}>{t('Installation identity, regional defaults and account security.')}</Typography></div>
    {error && <Alert severity="error">{error}</Alert>}
    {success && <Alert severity="success">{success}</Alert>}
    <Card variant="outlined"><CardContent><Stack spacing={2} sx={{ maxWidth: 680 }}>
      <div><Typography variant="h6">{t('Admin Studio language')}</Typography><Typography variant="body2" color="text.secondary">{t('This personal preference is stored in your user profile and does not change the site language.')}</Typography></div>
      <TextField select label={t('Interface language')} value={adminLocale} onChange={(event) => { const locale = event.target.value as AdminLocale; onAdminLocaleChange(locale).catch((reason: unknown) => setError(errorMessage(reason, 'Could not save the Admin Studio language.'))) }}>
        <MenuItem value="en">{t('English')}</MenuItem><MenuItem value="ru">{t('Russian')}</MenuItem>
      </TextField>
    </Stack></CardContent></Card>
    <Card variant="outlined"><CardContent>
      <Typography variant="h6">{t('Site identity')}</Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mt: .5 }}>{t('The site locale controls default public URLs and new content, not the Admin Studio language.')}</Typography>
      {!isOwner && <Alert severity="info" sx={{ my: 2 }}>{t('Only an owner can change site settings.')}</Alert>}
      {loading || !settings ? <Stack sx={{ py: 5, alignItems: 'center' }}><CircularProgress size={28} /></Stack> : (
        <Stack component="form" spacing={2} sx={{ mt: 2, maxWidth: 680 }} onSubmit={saveSettings}>
          <TextField label={t('Site name')} value={settings.siteName} disabled={!isOwner} required slotProps={{ htmlInput: { maxLength: 120 } }} onChange={(event) => setSettings({ ...settings, siteName: event.target.value })} />
          <TextField label={t('Default site locale')} value={settings.defaultLocale} disabled={!isOwner} required helperText={t('Examples: en, en-US, ru-RU')} onChange={(event) => setSettings({ ...settings, defaultLocale: event.target.value })} />
          <TextField label={t('Timezone')} value={settings.timezone} disabled={!isOwner} required helperText={t('IANA identifier, for example Europe/Moscow')} onChange={(event) => setSettings({ ...settings, timezone: event.target.value })} />
          {isOwner && <Button type="submit" variant="contained" disabled={saving} sx={{ alignSelf: 'flex-start' }}>{saving ? t('Saving…') : t('Save settings')}</Button>}
        </Stack>
      )}
    </CardContent></Card>
    <Card variant="outlined"><CardContent>
      <Typography variant="h6">{t('Change password')}</Typography>
      <Stack component="form" spacing={2} sx={{ mt: 2, maxWidth: 680 }} onSubmit={submitPassword}>
        <TextField label={t('Current password')} type="password" value={currentPassword} required onChange={(event) => setCurrentPassword(event.target.value)} />
        <TextField label={t('New password')} type="password" value={newPassword} required helperText={t('At least 12 characters')} slotProps={{ htmlInput: { minLength: 12 } }} onChange={(event) => setNewPassword(event.target.value)} />
        <TextField label={t('Confirm new password')} type="password" value={confirmPassword} required onChange={(event) => setConfirmPassword(event.target.value)} />
        <Button type="submit" variant="contained" disabled={changingPassword} sx={{ alignSelf: 'flex-start' }}>{changingPassword ? t('Changing…') : t('Change password')}</Button>
      </Stack>
    </CardContent></Card>
  </Stack>
}
