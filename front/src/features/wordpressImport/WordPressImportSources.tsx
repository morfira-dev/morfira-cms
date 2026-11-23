import { type FormEvent, useState } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import {
  Alert, Button, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, TextField, Typography,
} from '../../ui'
import { listWordPressImportSources, type WordPressImportRun, type WordPressImportSourceQuery } from './api'
import { MAX_SOURCE_KEY_LENGTH, isWordPressSourceKey } from './sourceKey'
import { useHistoryResult } from './useHistoryResult'
import { WordPressImportOutcomeChips } from './WordPressImportReportContent'

type ImportSourcesProps = {
  query: WordPressImportSourceQuery
  onQueryChange: (query: WordPressImportSourceQuery) => void
  onOpenRun: (run: WordPressImportRun) => void
  onOpenRuns: (source: string) => void
}

export function WordPressImportSources({ query, onQueryChange, onOpenRun, onOpenRuns }: ImportSourcesProps) {
  const { t } = useAdminLocale()
  const [draft, setDraft] = useState(query.prefix ?? '')
  const prefix = draft.trim().toLowerCase()
  const invalidPrefix = prefix !== '' && (prefix.length > MAX_SOURCE_KEY_LENGTH || !isWordPressSourceKey(prefix.replace(/-$/, '')))

  function applyPrefix(nextPrefix?: string) {
    if (query.page !== 1 || query.prefix !== nextPrefix) onQueryChange({ page: 1, prefix: nextPrefix })
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!invalidPrefix) {
      setDraft(prefix)
      applyPrefix(prefix || undefined)
    }
  }

  return (
    <>
      <Stack component="form" spacing={1.5} onSubmit={submit}>
        <TextField
          label={t('Source key prefix')}
          size="small"
          value={draft}
          error={invalidPrefix}
          helperText={invalidPrefix && t('Use lowercase letters, numbers and hyphens.')}
          slotProps={{ htmlInput: { maxLength: MAX_SOURCE_KEY_LENGTH } }}
          onChange={(event) => setDraft(event.target.value)}
        />
        <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap' }}>
          <Button type="submit" variant="contained" disabled={invalidPrefix}>{t('Apply filters')}</Button>
          <Button onClick={() => { setDraft(''); applyPrefix() }}>{t('Clear filters')}</Button>
        </Stack>
      </Stack>
      <ImportSourcesPage
        key={JSON.stringify(query)}
        query={query}
        onQueryChange={onQueryChange}
        onOpenRun={onOpenRun}
        onOpenRuns={onOpenRuns}
      />
    </>
  )
}

function ImportSourcesPage({ query, onQueryChange, onOpenRun, onOpenRuns }: ImportSourcesProps) {
  const { t, errorMessage, formatDateTime } = useAdminLocale()
  const { result, retry } = useHistoryResult(listWordPressImportSources, query)

  if (!result) return <Typography role="status" color="text.secondary">{t('Loading migration sources…')}</Typography>
  if ('error' in result) {
    return (
      <Alert severity="error" action={<Button color="inherit" onClick={retry}>{t('Retry')}</Button>}>
        {errorMessage(result.error, 'Could not load migration sources.')}
      </Alert>
    )
  }

  const { items, hasMore } = result.data
  return (
    <>
      {items.length === 0 ? (
        <Alert severity="info">{t(query.prefix ? 'No sources match the prefix.' : 'No migration sources yet.')}</Alert>
      ) : (
        <TableContainer>
          <Table size="small" aria-label={t('Migration sources')}>
            <TableHead>
              <TableRow>
                <TableCell>{t('Source key')}</TableCell>
                <TableCell align="right">{t('Recorded runs')}</TableCell>
                <TableCell align="right">{t('Studio runs')}</TableCell>
                <TableCell align="right">{t('CLI runs')}</TableCell>
                <TableCell>{t('Latest run')}</TableCell>
                <TableCell>{t('Import outcomes')}</TableCell>
                <TableCell aria-label={t('Source actions')} />
              </TableRow>
            </TableHead>
            <TableBody>
              {items.map(({ source, latestRun, runCount, studioRuns, cliRuns }) => (
                <TableRow key={source}>
                  <TableCell component="th" scope="row" sx={{ overflowWrap: 'anywhere' }}>{source}</TableCell>
                  <TableCell align="right">{runCount}</TableCell>
                  <TableCell align="right">{studioRuns}</TableCell>
                  <TableCell align="right">{cliRuns}</TableCell>
                  <TableCell><time dateTime={latestRun.completedAt}>{formatDateTime(latestRun.completedAt)}</time></TableCell>
                  <TableCell>
                    {Object.keys(latestRun.counts).length === 0 ? '—' : (
                      <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap' }}>
                        <WordPressImportOutcomeChips counts={latestRun.counts} />
                      </Stack>
                    )}
                  </TableCell>
                  <TableCell>
                    <Stack direction="row" spacing={0.5} useFlexGap sx={{ flexWrap: 'wrap' }}>
                      <Button size="small" onClick={() => onOpenRun(latestRun)}>{t('Open latest report')}</Button>
                      <Button size="small" onClick={() => onOpenRuns(source)}>{t('View source runs')}</Button>
                    </Stack>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}
      {(query.page > 1 || hasMore) && (
        <Stack direction="row" spacing={1} useFlexGap sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
          <Button size="small" disabled={query.page === 1} onClick={() => onQueryChange({ ...query, page: query.page - 1 })}>
            {t('Previous report page')}
          </Button>
          <Typography variant="body2">{t('Page {{page}}', { page: query.page })}</Typography>
          <Button size="small" disabled={!hasMore} onClick={() => onQueryChange({ ...query, page: query.page + 1 })}>
            {t('Next report page')}
          </Button>
        </Stack>
      )}
    </>
  )
}
