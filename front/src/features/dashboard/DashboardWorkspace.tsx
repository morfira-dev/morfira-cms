import { useCallback, useEffect, useState } from 'react'
import { Alert, Button, Card, CardContent, CircularProgress, Stack, Typography } from '../../ui'
import { getDashboardCounts, type DashboardCounts } from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'

export function DashboardWorkspace({ onNavigate }: { onNavigate: (path: string) => void }) {
  const { t } = useAdminLocale()
  const [counts, setCounts] = useState<DashboardCounts | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    const controller = new AbortController()
    setLoading(true); setError(null)
    getDashboardCounts(controller.signal)
      .then(setCounts)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : t('Could not load the content overview.'))
      })
      .finally(() => setLoading(false))
    return () => controller.abort()
  }, [t])

  useEffect(() => load(), [load])

  return <Stack spacing={3}>
    <div><Typography component="h1" variant="h1">{t('Dashboard')}</Typography><Typography color="text.secondary" sx={{ mt: .75 }}>{t('A live overview of the content managed by this installation.')}</Typography></div>
    {error && <Alert severity="error" action={<Button color="inherit" size="small" onClick={load}>{t('Retry')}</Button>}>{error}</Alert>}
    {loading ? <Stack sx={{ py: 8, alignItems: 'center' }}><CircularProgress size={30} /></Stack> : counts && (
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
        <CountCard label={t('Pages')} count={counts.pages} path="/admin/pages" onNavigate={onNavigate} />
        <CountCard label={t('Media assets')} count={counts.media} path="/admin/media" onNavigate={onNavigate} />
        <CountCard label={t('Content types')} count={counts.contentTypes} path="/admin/content-types" onNavigate={onNavigate} />
        <CountCard label={t('Taxonomies')} count={counts.taxonomies} path="/admin/taxonomies" onNavigate={onNavigate} />
      </Stack>
    )}
  </Stack>
}

function CountCard({ label, count, path, onNavigate }: { label: string; count: number; path: string; onNavigate: (path: string) => void }) {
  const { t } = useAdminLocale()
  return <Card variant="outlined" sx={{ flex: 1 }}><CardContent><Typography color="text.secondary">{label}</Typography><Typography variant="h3" sx={{ my: 1 }}>{count}</Typography><Button size="small" onClick={() => onNavigate(path)}>{t('Open')}</Button></CardContent></Card>
}
