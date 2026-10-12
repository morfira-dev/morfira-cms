import { useEffect, useState } from 'react'
import { Alert, Card, CardContent, Chip, CircularProgress, Stack, Typography } from '../../ui'
import { getExtensions, type Extension } from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'

export function ExtensionsWorkspace() {
  const { t } = useAdminLocale()
  const [items, setItems] = useState<Extension[]>([])
  const [apiVersion, setApiVersion] = useState<number>()
  const [error, setError] = useState<string>()
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const controller = new AbortController()
    getExtensions(controller.signal)
      .then((catalog) => { setItems(catalog.items); setApiVersion(catalog.apiVersion) })
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : t('Could not load extensions.'))
      })
      .finally(() => setLoading(false))
    return () => controller.abort()
  }, [t])

  return <Stack spacing={3}>
    <div><Typography component="h1" variant="h1">{t('Extensions')}</Typography><Typography color="text.secondary" sx={{ mt: .75 }}>{t('Trusted deployment-installed packages using extension API v{{version}}.', { version: apiVersion ?? '…' })}</Typography></div>
    {error && <Alert severity="error">{error}</Alert>}
    {loading ? <CircularProgress size={28} /> : items.map((extension) => <Card key={extension.id} variant="outlined"><CardContent><Stack spacing={1}><Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}><Typography variant="h6">{extension.name}</Typography><Chip size="small" color="success" label={extension.status === 'enabled' ? t('enabled') : extension.status} /></Stack><Typography color="text.secondary">{extension.id} · {extension.version} · {extension.provider}</Typography><Stack direction="row" spacing={1}>{extension.capabilities.map((capability) => <Chip key={capability} size="small" variant="outlined" label={capability} />)}</Stack></Stack></CardContent></Card>)}
  </Stack>
}
