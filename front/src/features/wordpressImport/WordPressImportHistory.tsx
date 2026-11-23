import { type FormEvent, useEffect, useState } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import {
  Alert, Button, Card, CardContent, MenuItem, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, TextField, Typography,
} from '../../ui'
import { scheduleAbortableLoad } from '../../utils/scheduleAbortableLoad'
import { getWordPressImportRun, listWordPressImportRuns, type WordPressImportRun, type WordPressImportRunQuery } from './api'
import { isWordPressSourceKey, MAX_SOURCE_KEY_LENGTH } from './sourceKey'
import { WordPressImportOutcomeChips, WordPressImportReportContent } from './WordPressImportReportContent'

type HistoryResult<T> = { data: T } | { error: unknown }
type RunFilters = Omit<WordPressImportRunQuery, 'page'>
type ImportRunSource = Pick<WordPressImportRun, 'source' | 'input'>

const inputLabels = { wxr: 'WXR file', rest: 'WordPress REST API' } as const
const channelLabels = { studio: 'Admin Studio', cli: 'Command line (CLI)' } as const

function useHistoryResult<Input, Result>(load: (input: Input, signal: AbortSignal) => Promise<Result>, input: Input) {
  const [result, setResult] = useState<HistoryResult<Result> | null>(null)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => scheduleAbortableLoad((signal) => {
    load(input, signal).then(
      (data) => { if (!signal.aborted) setResult({ data }) },
      (error: unknown) => { if (!signal.aborted) setResult({ error }) },
    )
  }), [attempt, input, load])

  function retry() {
    setResult(null)
    setAttempt((previous) => previous + 1)
  }

  return { result, retry }
}

function ImportRunFilters({ applied, onApply }: {
  applied: RunFilters
  onApply: (filters: RunFilters) => void
}) {
  const { t } = useAdminLocale()
  const [draft, setDraft] = useState<RunFilters>({ source: applied.source, input: applied.input, scope: applied.scope, channel: applied.channel })
  const invalidSource = draft.source !== undefined && !isWordPressSourceKey(draft.source)

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!invalidSource) onApply(draft)
  }

  return (
    <Stack component="form" spacing={1.5} onSubmit={submit}>
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ '& > *': { flex: 1 } }}>
        <TextField
          label={t('Source key')}
          size="small"
          value={draft.source ?? ''}
          error={invalidSource}
          helperText={invalidSource && t('Use lowercase letters, numbers and hyphens.')}
          slotProps={{ htmlInput: { maxLength: MAX_SOURCE_KEY_LENGTH } }}
          onChange={(event) => setDraft({ ...draft, source: event.target.value || undefined })}
        />
        <TextField
          select
          label={t('Import format')}
          size="small"
          value={draft.input ?? 'any'}
          onChange={(event) => {
            const input = event.target.value
            setDraft({ ...draft, input: input === 'wxr' || input === 'rest' ? input : undefined })
          }}
        >
          <MenuItem value="any">{t('All formats')}</MenuItem>
          <MenuItem value="wxr">{t(inputLabels.wxr)}</MenuItem>
          <MenuItem value="rest">{t(inputLabels.rest)}</MenuItem>
        </TextField>
        <TextField
          select
          label={t('Import scope')}
          size="small"
          value={draft.scope ?? 'any'}
          onChange={(event) => {
            const scope = event.target.value
            setDraft({ ...draft, scope: scope === 'all' || scope === 'selection' ? scope : undefined })
          }}
        >
          <MenuItem value="any">{t('All scopes')}</MenuItem>
          <MenuItem value="all">{t('Full source')}</MenuItem>
          <MenuItem value="selection">{t('Selected items')}</MenuItem>
        </TextField>
        <TextField
          select
          label={t('Import channel')}
          size="small"
          value={draft.channel ?? 'any'}
          onChange={(event) => {
            const channel = event.target.value
            setDraft({ ...draft, channel: channel === 'studio' || channel === 'cli' ? channel : undefined })
          }}
        >
          <MenuItem value="any">{t('All channels')}</MenuItem>
          <MenuItem value="studio">{t(channelLabels.studio)}</MenuItem>
          <MenuItem value="cli">{t(channelLabels.cli)}</MenuItem>
        </TextField>
      </Stack>
      <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap' }}>
        <Button type="submit" variant="contained" disabled={invalidSource}>{t('Apply filters')}</Button>
        <Button onClick={() => { setDraft({}); onApply({}) }}>{t('Clear filters')}</Button>
      </Stack>
    </Stack>
  )
}

function ImportRunsPage({ query, onPage, onOpen }: {
  query: WordPressImportRunQuery
  onPage: (page: number) => void
  onOpen: (run: WordPressImportRun) => void
}) {
  const { t, errorMessage, formatDateTime } = useAdminLocale()
  const { result, retry } = useHistoryResult(listWordPressImportRuns, query)

  if (!result) return <Typography role="status" color="text.secondary">{t('Loading import history…')}</Typography>
  if ('error' in result) {
    return (
      <Alert severity="error" action={<Button color="inherit" onClick={retry}>{t('Retry')}</Button>}>
        {errorMessage(result.error, 'Could not load import history.')}
      </Alert>
    )
  }
  const { items, hasMore } = result.data
  const { page, source, input, scope, channel } = query

  return (
    <>
      {items.length === 0 ? (
        <Alert severity="info">
          {t(source || input || scope || channel ? 'No completed imports match the selected filters.' : 'No completed imports yet.')}
        </Alert>
      ) : (
        <TableContainer>
          <Table size="small" aria-label={t('Import history')}>
            <TableHead>
              <TableRow>
                <TableCell>{t('Completed at')}</TableCell>
                <TableCell>{t('Source key')}</TableCell>
                <TableCell>{t('Import format')}</TableCell>
                <TableCell>{t('Import scope')}</TableCell>
                <TableCell>{t('Import channel')}</TableCell>
                <TableCell>{t('Import outcomes')}</TableCell>
                <TableCell aria-label={t('View report')} />
              </TableRow>
            </TableHead>
            <TableBody>
              {items.map((run) => (
                <TableRow key={run.id}>
                  <TableCell><time dateTime={run.completedAt}>{formatDateTime(run.completedAt)}</time></TableCell>
                  <TableCell component="th" scope="row" sx={{ overflowWrap: 'anywhere' }}>{run.source}</TableCell>
                  <TableCell>{t(inputLabels[run.input])}</TableCell>
                  <TableCell>{t(run.scope === 'selection' ? 'Selected items' : 'Full source')}</TableCell>
                  <TableCell>{t(channelLabels[run.channel])}</TableCell>
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

function ImportRunReport({ run, onBack, onResume }: {
  run: WordPressImportRun
  onBack: () => void
  onResume: (source: ImportRunSource) => void
}) {
  const { t, errorMessage, formatDateTime } = useAdminLocale()
  const { result, retry } = useHistoryResult(getWordPressImportRun, run.id)
  const report = result && 'data' in result ? result.data : null

  return (
    <Stack spacing={2} component="section">
      <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between' }}>
        <Typography component="h3" variant="subtitle1">{t('Import run report')}</Typography>
        <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap' }}>
          <Button onClick={onBack}>{t('Back to history')}</Button>
          {report && (
            <Button variant="outlined" onClick={() => onResume({ source: run.source, input: run.input })}>
              {t('Continue from this source')}
            </Button>
          )}
        </Stack>
      </Stack>
      <Typography color="text.secondary">
        {run.source} · <time dateTime={run.completedAt}>{formatDateTime(run.completedAt)}</time> · {t(inputLabels[run.input])} · {t(run.scope === 'selection' ? 'Selected items' : 'Full source')} · {t(channelLabels[run.channel])}
      </Typography>
      {!result && <Typography role="status" color="text.secondary">{t('Loading import report…')}</Typography>}
      {result && 'error' in result && (
        <Alert severity="error" action={<Button color="inherit" onClick={retry}>{t('Retry')}</Button>}>
          {errorMessage(result.error, 'Could not load this import report.')}
        </Alert>
      )}
      {report && <WordPressImportReportContent report={report} />}
    </Stack>
  )
}

export function WordPressImportHistory({ initialSource, onResume }: {
  initialSource?: string
  onResume: (source: ImportRunSource) => void
}) {
  const { t } = useAdminLocale()
  const [query, setQuery] = useState<WordPressImportRunQuery>({ page: 1, source: initialSource })
  const [run, setRun] = useState<WordPressImportRun | null>(null)

  function applyFilters({ source, input, scope, channel }: RunFilters) {
    setQuery((current) =>
      current.page === 1 && current.source === source && current.input === input && current.scope === scope && current.channel === channel
        ? current
        : { page: 1, source, input, scope, channel },
    )
  }

  return (
    <Card variant="outlined">
      <CardContent>
        <Stack spacing={2}>
          <Typography component="h2" variant="h6">{t('Import history')}</Typography>
          {run
            ? <ImportRunReport key={run.id} run={run} onBack={() => setRun(null)} onResume={onResume} />
            : (
              <>
                <ImportRunFilters applied={query} onApply={applyFilters} />
                <ImportRunsPage
                  key={JSON.stringify(query)}
                  query={query}
                  onPage={(page) => setQuery((current) => ({ ...current, page }))}
                  onOpen={setRun}
                />
              </>
            )}
        </Stack>
      </CardContent>
    </Card>
  )
}
