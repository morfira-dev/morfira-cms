import { type ChangeEvent, type FormEvent, useEffect, useId, useRef, useState } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import {
  Alert, Box, Button, Card, CardContent, Checkbox, FormControlLabel, MenuItem, Stack, TextField, Typography,
} from '../../ui'
import { importWordPress, importWordPressRest, type WordPressImportMode, type WordPressImportReport } from './api'
import { WordPressImportHistory } from './WordPressImportHistory'
import { WordPressImportReportContent } from './WordPressImportReportContent'
import { isWordPressRestNamespaceUrl, MAX_REST_URL_LENGTH, parseWordPressMediaHosts } from './restSource'
import { isWordPressSourceKey, MAX_SOURCE_KEY_LENGTH } from './sourceKey'

const MAX_WXR_BYTES = 8 * 1024 * 1024
const restImportErrorKeys = [
  'Could not read the public WordPress REST API.',
  'Download media must be a boolean.',
  'Allowed media hosts require public image downloads.',
  'Provide a list of up to eight WordPress media hostnames.',
  'WordPress media hosts must be exact public DNS hostnames.',
] as const

function WordPressImportWizard({ onApplied }: { onApplied: () => void }) {
  const { errorMessage, t } = useAdminLocale()
  const [input, setInput] = useState<WordPressImportReport['input']>('wxr')
  const [file, setFile] = useState<File | null>(null)
  const [apiUrl, setApiUrl] = useState('')
  const [downloadMedia, setDownloadMedia] = useState(false)
  const [mediaHostInput, setMediaHostInput] = useState('')
  const [source, setSource] = useState('')
  const [report, setReport] = useState<WordPressImportReport | null>(null)
  const [excludedIds, setExcludedIds] = useState<ReadonlySet<string>>(new Set())
  const [pendingMode, setPendingMode] = useState<WordPressImportMode | null>(null)
  const [error, setError] = useState<string | null>(null)
  const pending = useRef<AbortController | null>(null)
  const mediaHelpId = useId()

  useEffect(() => () => pending.current?.abort(), [])

  const sourceValid = isWordPressSourceKey(source)
  const apiUrlValid = input === 'rest' && isWordPressRestNamespaceUrl(apiUrl)
  const allowedMediaHosts = input === 'rest' && downloadMedia ? parseWordPressMediaHosts(mediaHostInput) : []
  const requestSource = input === 'wxr'
    ? file && { input, file }
    : apiUrlValid && { input, apiUrl }
  const canSubmit = Boolean(requestSource && sourceValid && allowedMediaHosts !== null && pendingMode === null)
  const plannedItems = report?.mode === 'dry-run' ? report.items.filter((item) => item.state === 'planned') : []
  const hasPlannedContent = Boolean(report && (plannedItems.length > 0
    || report.taxonomy.terms.some((term) => term.state === 'planned')
    || report.taxonomy.assignments.some((assignment) => assignment.state === 'planned')
    || report.authors.some((author) => author.state === 'planned')))
  const hasPlannedImages = input === 'rest' && downloadMedia && report?.media.some((item) => item.state === 'planned')
  const hasPlannedChanges = hasPlannedContent || hasPlannedImages
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
    if (!requestSource || !sourceValid || allowedMediaHosts === null || pending.current) return
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
      const next = requestSource.input === 'wxr'
        ? await importWordPress(requestSource.file, source, mode, controller.signal, selectedIds)
        : await importWordPressRest(requestSource.apiUrl, source, mode, controller.signal, { selectedIds, downloadMedia, allowedMediaHosts })
      if (!controller.signal.aborted) {
        setReport(next)
        setExcludedIds(new Set())
        if (mode === 'apply') onApplied()
      }
    } catch (reason) {
      if (!controller.signal.aborted) {
        clearReport()
        const knownError = reason instanceof Error && restImportErrorKeys.find((key) => key === reason.message)
        setError(knownError ? t(knownError) : errorMessage(reason, mode === 'dry-run' ? 'Could not preview the WordPress import.' : 'Could not apply the WordPress import.'))
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
            <TextField
              select
              label={t('Import source')}
              value={input}
              disabled={pendingMode !== null}
              onChange={(event) => {
                setInput(event.target.value === 'rest' ? 'rest' : 'wxr')
                clearReport()
                setError(null)
              }}
            >
              <MenuItem value="wxr">{t('WXR file')}</MenuItem>
              <MenuItem value="rest">{t('WordPress REST API')}</MenuItem>
            </TextField>
            {input === 'wxr' ? (
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
            ) : (
              <>
                <TextField
                  type="url"
                  label={t('WordPress REST API URL')}
                  value={apiUrl}
                  disabled={pendingMode !== null}
                  error={apiUrl.length > 0 && !apiUrlValid}
                  helperText={t('Enter the full HTTPS URL ending in /wp-json/wp/v2.')}
                  slotProps={{ htmlInput: { maxLength: MAX_REST_URL_LENGTH } }}
                  onChange={(event) => {
                    setApiUrl(event.target.value)
                    clearReport()
                    setError(null)
                  }}
                />
                <Box>
                  <FormControlLabel
                    label={t('Download public images')}
                    control={(
                      <Checkbox
                        checked={downloadMedia}
                        disabled={pendingMode !== null}
                        slotProps={{ input: { 'aria-describedby': mediaHelpId } }}
                        onChange={(_, checked) => {
                          setDownloadMedia(checked)
                          clearReport()
                          setError(null)
                        }}
                      />
                    )}
                  />
                  <Typography id={mediaHelpId} variant="body2" color="text.secondary">
                    {t('Studio can download up to eight new images per import, including images outside the selected pages and posts. Use the CLI for larger imports or private sources.')}
                  </Typography>
                </Box>
                {downloadMedia && (
                  <TextField
                    label={t('Allowed CDN hosts')}
                    multiline
                    minRows={2}
                    maxRows={8}
                    value={mediaHostInput}
                    disabled={pendingMode !== null}
                    error={allowedMediaHosts === null}
                    helperText={t(allowedMediaHosts === null
                      ? 'Enter up to eight exact public DNS hostnames, one per line.'
                      : 'Optional. Enter up to eight CDN hostnames, one per line. The REST API host is always allowed.')}
                    onChange={(event) => {
                      setMediaHostInput(event.target.value)
                      clearReport()
                      setError(null)
                    }}
                  />
                )}
              </>
            )}
            <TextField
              label={t('Source key')}
              value={source}
              disabled={pendingMode !== null}
              error={source.length > 0 && !sourceValid}
              helperText={t('Reuse the same key for this WordPress site. Use lowercase letters, numbers and hyphens.')}
              slotProps={{ htmlInput: { maxLength: MAX_SOURCE_KEY_LENGTH } }}
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
                  <Typography color="text.secondary">
                    {t(hasPlannedContent ? 'Confirm this preview to create drafts. Existing drafts stay unchanged.' : 'Confirm this preview to import images.')}
                  </Typography>
                  {excludedIds.size > 0 && hasSelection && (
                    <Typography color="text.secondary">
                      {t(input === 'rest' && downloadMedia
                        ? 'Drafts will be created for the selected items. Image downloads cover the whole source.'
                        : 'Only selected drafts and their related data will be imported.')}
                    </Typography>
                  )}
                  <Button variant="contained" loading={pendingMode === 'apply'} disabled={!canSubmit || !hasSelection} onClick={() => void run('apply')}>
                    {t(hasPlannedContent ? 'Create drafts' : 'Import images')}
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
              {t('Preview a WordPress source and create drafts for review before publication.')}
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
