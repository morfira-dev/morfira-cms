import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import { Alert, Box, Button, Chip, Stack } from '../../ui'
import type { WordPressImportReport } from './api'
import { WordPressImportDetails } from './WordPressImportDetails'
import { WordPressImportItems, type ImportItemSelection } from './WordPressImportItems'
import { importStatusKey } from './reportPresentation'

function downloadReport(report: WordPressImportReport) {
  const file = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(file)
  const link = document.createElement('a')
  link.href = url
  const inputSuffix = report.input === 'rest' ? '-rest' : ''
  link.download = `wordpress-import-${report.source}${inputSuffix}-${report.mode}.json`
  link.click()
  window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}

export function WordPressImportOutcomeChips({ counts }: { counts: WordPressImportReport['counts'] }) {
  const { t } = useAdminLocale()
  return Object.entries(counts).map(([state, count]) => {
    const key = importStatusKey(state)
    return <Chip key={state} label={t('{{label}} · {{count}}', { label: key ? t(key) : state, count })} />
  })
}

export function WordPressImportReportContent({ report, selection }: {
  report: WordPressImportReport
  selection?: ImportItemSelection
}) {
  const { t } = useAdminLocale()

  return (
    <>
      <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap' }}>
        <WordPressImportOutcomeChips counts={report.counts} />
        {Object.entries(report.skippedTypes).map(([type, count]) => (
          <Chip key={type} variant="outlined" label={t('Skipped type {{type}} · {{count}}', { type, count })} />
        ))}
        {report.taxonomy.terms.length > 0 && <Chip variant="outlined" label={t('Taxonomy terms · {{count}}', { count: report.taxonomy.terms.length })} />}
        {report.taxonomy.assignments.length > 0 && <Chip variant="outlined" label={t('Taxonomy assignments · {{count}}', { count: report.taxonomy.assignments.length })} />}
        {report.authors.length > 0 && <Chip variant="outlined" label={t('Author profiles · {{count}}', { count: report.authors.length })} />}
        {report.input === 'rest' && report.media.length > 0 && (
          <Chip variant="outlined" label={t('{{label}} · {{count}}', { label: t('Imported attachment details'), count: report.media.length })} />
        )}
      </Stack>
      {report.input === 'wxr' && report.media.length > 0 && (
        <Alert severity="info">
          {t('The export references {{count}} attachments. Import their files with the command-line importer.', { count: report.media.length })}
        </Alert>
      )}
      <WordPressImportItems items={report.items} input={report.input} selection={selection} />
      <WordPressImportDetails report={report} />
      <Box>
        <Button variant="outlined" onClick={() => downloadReport(report)}>{t('Download JSON report')}</Button>
      </Box>
    </>
  )
}
