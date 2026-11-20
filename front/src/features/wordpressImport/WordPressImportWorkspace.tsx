import { type ChangeEvent, type FormEvent, useEffect, useRef, useState } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import {
  Alert, Box, Button, Card, CardContent, Stack, TextField, Typography,
} from '../../ui'
import { importWordPress, type WordPressImportMode, type WordPressImportReport } from './api'
import { WordPressImportHistory } from './WordPressImportHistory'
import { WordPressImportReportContent } from './WordPressImportReportContent'

const MAX_WXR_BYTES = 8 * 1024 * 1024

function WordPressImportWizard({ onApplied }: { onApplied: () => void }) {
  const { errorMessage, t } = useAdminLocale()
  const [file, setFile] = useState<File | null>(null)
  const [source, setSource] = useState('')
  const [report, setReport] = useState<WordPressImportReport | null>(null)
  const [excludedIds, setExcludedIds] = useState<ReadonlySet<string>>(new Set())
  const [pendingMode, setPendingMode] = useState<WordPressImportMode | null>(null)
  const [error, setError] = useState<string | null>(null)
  const pending = useRef<AbortController | null>(null)

  useEffect(() => () => pending.current?.abort(), [])

  const sourceValid = source.length <= 80 && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(source)
  const canSubmit = Boolean(file && sourceValid && pendingMode === null)
  const plannedItems = report?.mode === 'dry-run' ? report.items.filter((item) => item.state === 'planned') : []
  const hasPlannedChanges = Boolean(report && (plannedItems.length > 0
    || report.taxonomy.terms.some((term) => term.state === 'planned')
    || report.taxonomy.assignments.some((assignment) => assignment.state === 'planned')
    || report.authors.some((author) => author.state === 'planned')))
  const hasSelection = plannedItems.length === 0 || excludedIds.size < plannedItems.length

  function clearReport() {
    setReport(null)
    setExcludedIds(new Set())
  }

  function changeSelection(ids: readonly string[], include: boolean) {
    if (pending.current) return
    setExcludedIds((previous) => {
      const next = new Set(previous)
      for (const id of ids) {
        if (include) next.delete(id)
        else next.add(id)
      }
      return next
    })
  }

  function chooseFile(event: ChangeEvent<HTMLInputElement>) {
    const selected = event.currentTarget.files?.[0] ?? null
    clearReport()
    if (selected && selected.size > MAX_WXR_BYTES) {
      event.currentTarget.value = ''
      setFile(null)
      setError(t('Choose a WXR file of 8 MiB or smaller.'))
      return
    }

    setFile(selected)
    setError(null)
  }

  async function run(mode: WordPressImportMode) {
    if (!file || !sourceValid || pending.current) return
    if (mode === 'apply' && (report?.mode !== 'dry-run' || !hasPlannedChanges || !hasSelection)) return

    const selectedIds = mode === 'apply' && excludedIds.size > 0
      ? plannedItems.filter((item) => !excludedIds.has(item.id)).map((item) => item.id)
      : undefined

    const controller = new AbortController()
    pending.current = controller
    setPendingMode(mode)
    setError(null)
    if (mode === 'dry-run') clearReport()

    try {
      const next = await importWordPress(file, source, mode, controller.signal, selectedIds)
      if (!controller.signal.aborted) {
        setReport(next)
        setExcludedIds(new Set())
        if (mode === 'apply') onApplied()
      }
    } catch (reason) {
      if (!controller.signal.aborted) {
        clearReport()
        setError(errorMessage(reason, mode === 'dry-run' ? 'Could not preview the WordPress import.' : 'Could not apply the WordPress import.'))
      }
    } finally {
      pending.current = null
      if (!controller.signal.aborted) setPendingMode(null)
    }
  }

  function preview(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    void run('dry-run')
  }

  return (
    <Stack spacing={3}>
      {error && <Alert severity="error">{error}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Stack component="form" spacing={2} onSubmit={preview} aria-busy={pendingMode !== null}>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ alignItems: { sm: 'center' } }}>
              <Button component="label" variant="outlined" disabled={pendingMode !== null}>
                {t('Choose WXR file')}
                <input
                  hidden
                  type="file"
                  aria-label={t('WXR export')}
                  accept=".xml,.wxr,application/xml,text/xml"
                  disabled={pendingMode !== null}
                  onChange={chooseFile}
                />
              </Button>
              <Typography color="text.secondary" sx={{ overflowWrap: 'anywhere' }}>{file?.name ?? t('XML export, up to 8 MiB')}</Typography>
            </Stack>
            <TextField
              label={t('Source key')}
              value={source}
              disabled={pendingMode !== null}
              error={source.length > 0 && !sourceValid}
              helperText={t('Reuse the same key for this WordPress site. Use lowercase letters, numbers and hyphens.')}
              slotProps={{ htmlInput: { maxLength: 80 } }}
              onChange={(event) => {
                setSource(event.target.value)
                clearReport()
                setError(null)
              }}
            />
            <Box>
              <Button type="submit" variant="contained" loading={pendingMode === 'dry-run'} disabled={!canSubmit}>
                {t('Preview import')}
              </Button>
            </Box>
          </Stack>
        </CardContent>
      </Card>

      {report && (
        <Card variant="outlined">
          <CardContent>
            <Stack spacing={2}>
              <Typography component="h2" variant="h6">
                {t(report.mode === 'dry-run' ? 'Import preview' : 'Import result')}
              </Typography>
              {report.mode === 'apply' && (
                <Alert severity="success">
                  {t(report.scope === 'selection'
                    ? 'Selected import completed. Preview again to import the remaining items.'
                    : 'Import completed. Review the report for created and skipped items.')}
                </Alert>
              )}
              <WordPressImportReportContent
                key={report.mode}
                report={report}
                selection={plannedItems.length > 0 ? {
                  excludedIds,
                  plannedCount: plannedItems.length,
                  disabled: pendingMode !== null,
                  onChange: changeSelection,
                } : undefined}
              />
              {report.mode === 'dry-run' && hasPlannedChanges && (
                <Stack spacing={1} sx={{ alignItems: 'flex-start' }}>
                  <Typography color="text.secondary">{t('Confirm this preview to create drafts. Existing drafts stay unchanged.')}</Typography>
                  {excludedIds.size > 0 && hasSelection && (
                    <Typography color="text.secondary">{t('Only selected drafts and their related data will be imported.')}</Typography>
                  )}
                  <Button variant="contained" loading={pendingMode === 'apply'} disabled={!canSubmit || !hasSelection} onClick={() => void run('apply')}>
                    {t('Create drafts')}
                  </Button>
                </Stack>
              )}
            </Stack>
          </CardContent>
        </Card>
      )}
    </Stack>
  )
}

export function WordPressImportWorkspace() {
  const { t } = useAdminLocale()
  const [historyOpen, setHistoryOpen] = useState(false)
  const [historyVersion, setHistoryVersion] = useState(0)

  return (
    <Stack spacing={3}>
      <Stack direction="row" spacing={2} useFlexGap sx={{ alignItems: 'flex-start', flexWrap: 'wrap', justifyContent: 'space-between' }}>
        <div>
          <Typography component="h1" variant="h1">{t('WordPress import')}</Typography>
          {!historyOpen && (
            <Typography color="text.secondary" sx={{ mt: 0.75 }}>
              {t('Preview a WordPress export and create drafts for review before publication.')}
            </Typography>
          )}
        </div>
        <Button variant="outlined" onClick={() => setHistoryOpen((open) => !open)}>
          {t(historyOpen ? 'Back to import' : 'Import history')}
        </Button>
      </Stack>
      <Box hidden={historyOpen}>
        <WordPressImportWizard onApplied={() => setHistoryVersion((version) => version + 1)} />
      </Box>
      {historyOpen && <WordPressImportHistory key={historyVersion} />}
    </Stack>
  )
}
