import { useEffect, useState } from 'react'
import { Alert, Button, Card, CardContent, CircularProgress, Stack, Typography } from '../../ui'
import { getDashboardCounts, type DashboardCounts } from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import { scheduleAbortableLoad } from '../../utils/scheduleAbortableLoad'

export function DashboardWorkspace({ onNavigate }: { onNavigate: (path: string) => void }) {
  const { errorMessage, t } = useAdminLocale()
  const [result, setResult] = useState<{ counts: DashboardCounts } | { error: unknown } | null>(null)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => scheduleAbortableLoad((signal) => {
    getDashboardCounts(signal).then(
      (counts) => { if (!signal.aborted) setResult({ counts }) },
      (error: unknown) => { if (!signal.aborted) setResult({ error }) },
    )
  }), [attempt])

  function retry() {
    setResult(null)
    setAttempt((previous) => previous + 1)
  }

  const counts = result && 'counts' in result ? result.counts : null

  return <Stack spacing={3}>
    <div><Typography component="h1" variant="h1">{t('Dashboard')}</Typography><Typography color="text.secondary" sx={{ mt: .75 }}>{t('A live overview of the content managed by this installation.')}</Typography></div>
    {result && 'error' in result && (
      <Alert severity="error" action={<Button color="inherit" size="small" onClick={retry}>{t('Retry')}</Button>}>
        {errorMessage(result.error, 'Could not load the content overview.')}
      </Alert>
    )}
    {result === null ? <Stack sx={{ py: 8, alignItems: 'center' }}><CircularProgress size={30} /></Stack> : counts && (
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
