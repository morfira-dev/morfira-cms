import { useEffect, useState } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import {
  Alert, Button, Card, CardContent, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Typography,
} from '../../ui'
import { getWordPressImportRun, listWordPressImportRuns, type WordPressImportRun } from './api'
import { WordPressImportOutcomeChips, WordPressImportReportContent } from './WordPressImportReportContent'

type HistoryResult<T> = { data: T } | { error: unknown }

function useHistoryResult<Input, Result>(load: (input: Input, signal: AbortSignal) => Promise<Result>, input: Input) {
  const [result, setResult] = useState<HistoryResult<Result> | null>(null)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    load(input, controller.signal).then(
      (data) => { if (!controller.signal.aborted) setResult({ data }) },
      (error: unknown) => { if (!controller.signal.aborted) setResult({ error }) },
    )
    return () => controller.abort()
  }, [attempt, input, load])

  function retry() {
    setResult(null)
    setAttempt((previous) => previous + 1)
  }

  return { result, retry }
}

function ImportRunsPage({ page, onPage, onOpen }: {
  page: number
  onPage: (page: number) => void
  onOpen: (run: WordPressImportRun) => void
}) {
  const { t, errorMessage, formatDateTime } = useAdminLocale()
  const { result, retry } = useHistoryResult(listWordPressImportRuns, page)

  if (!result) return <Typography role="status" color="text.secondary">{t('Loading import history…')}</Typography>
  if ('error' in result) {
    return (
      <Alert severity="error" action={<Button color="inherit" onClick={retry}>{t('Retry')}</Button>}>
        {errorMessage(result.error, 'Could not load import history.')}
      </Alert>
    )
  }
  const { items, hasMore } = result.data

  return (
    <>
      {items.length === 0 ? (
        <Alert severity="info">{t('No completed imports yet.')}</Alert>
      ) : (
        <TableContainer>
          <Table size="small" aria-label={t('Import history')}>
            <TableHead>
              <TableRow>
                <TableCell>{t('Completed at')}</TableCell>
                <TableCell>{t('Source key')}</TableCell>
                <TableCell>{t('Import scope')}</TableCell>
                <TableCell>{t('Import outcomes')}</TableCell>
                <TableCell aria-label={t('View report')} />
              </TableRow>
            </TableHead>
            <TableBody>
              {items.map((run) => (
                <TableRow key={run.id}>
                  <TableCell><time dateTime={run.completedAt}>{formatDateTime(run.completedAt)}</time></TableCell>
                  <TableCell component="th" scope="row" sx={{ overflowWrap: 'anywhere' }}>{run.source}</TableCell>
                  <TableCell>{t(run.scope === 'selection' ? 'Selected items' : 'Full source')}</TableCell>
                  <TableCell>
                    {Object.keys(run.counts).length === 0 ? '—' : (
                      <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap' }}>
                        <WordPressImportOutcomeChips counts={run.counts} />
                      </Stack>
                    )}
                  </TableCell>
                  <TableCell><Button size="small" onClick={() => onOpen(run)}>{t('View report')}</Button></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}
      {(page > 1 || hasMore) && (
        <Stack direction="row" spacing={1} useFlexGap sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
          <Button size="small" disabled={page === 1} onClick={() => onPage(page - 1)}>{t('Previous report page')}</Button>
          <Typography variant="body2">{t('Page {{page}}', { page })}</Typography>
          <Button size="small" disabled={!hasMore} onClick={() => onPage(page + 1)}>{t('Next report page')}</Button>
        </Stack>
      )}
    </>
  )
}

function ImportRunReport({ run, onBack }: { run: WordPressImportRun; onBack: () => void }) {
  const { t, errorMessage, formatDateTime } = useAdminLocale()
  const { result, retry } = useHistoryResult(getWordPressImportRun, run.id)

  return (
    <Stack spacing={2} component="section">
      <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between' }}>
        <Typography component="h3" variant="subtitle1">{t('Import run report')}</Typography>
        <Button onClick={onBack}>{t('Back to history')}</Button>
      </Stack>
      <Typography color="text.secondary">
        {run.source} · <time dateTime={run.completedAt}>{formatDateTime(run.completedAt)}</time> · {t(run.scope === 'selection' ? 'Selected items' : 'Full source')}
      </Typography>
      {!result && <Typography role="status" color="text.secondary">{t('Loading import report…')}</Typography>}
      {result && 'error' in result && (
        <Alert severity="error" action={<Button color="inherit" onClick={retry}>{t('Retry')}</Button>}>
          {errorMessage(result.error, 'Could not load this import report.')}
        </Alert>
      )}
      {result && 'data' in result && <WordPressImportReportContent report={result.data} />}
    </Stack>
  )
}

export function WordPressImportHistory() {
  const { t } = useAdminLocale()
  const [page, setPage] = useState(1)
  const [run, setRun] = useState<WordPressImportRun | null>(null)

  return (
    <Card variant="outlined">
      <CardContent>
        <Stack spacing={2}>
          <Typography component="h2" variant="h6">{t('Import history')}</Typography>
          {run
            ? <ImportRunReport key={run.id} run={run} onBack={() => setRun(null)} />
            : <ImportRunsPage key={page} page={page} onPage={setPage} onOpen={setRun} />}
        </Stack>
      </CardContent>
    </Card>
  )
}
