import { useState } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { MessageKey } from '../../i18n/messages'
import {
  MenuItem, Stack, Table, TableBody, TableCell, TableContainer,
  TableHead, TablePagination, TableRow, TextField, Typography,
} from '../../ui'
import type { WordPressImportReport } from './api'
import { IMPORT_REPORT_PAGE_SIZE, importPaginationLabels, importStatusKey } from './reportPresentation'

type DetailRow = {
  identifier: string
  reference?: string
  state: string
  reason?: string
}

type DetailGroup = {
  id: string
  label: MessageKey
  rows: DetailRow[]
}

export function WordPressImportDetails({ report }: { report: WordPressImportReport }) {
  const { t } = useAdminLocale()
  const [groupId, setGroupId] = useState('')
  const [page, setPage] = useState(0)
  const groups: DetailGroup[] = [
    {
      id: 'media',
      label: 'Imported attachment details',
      rows: report.media.map(({ id, assetId, state, reason }) => ({ identifier: id, reference: assetId, state, reason })),
    },
    {
      id: 'terms',
      label: 'Taxonomy term details',
      rows: report.taxonomy.terms.map(({ taxonomy, slug, state }) => ({ identifier: slug, reference: taxonomy, state })),
    },
    {
      id: 'assignments',
      label: 'Taxonomy assignment details',
      rows: report.taxonomy.assignments.map(({ taxonomy, slug, entryId, state }) => ({ identifier: `${taxonomy}/${slug}`, reference: entryId, state })),
    },
    {
      id: 'authors',
      label: 'Author profile details',
      rows: report.authors.map(({ profileId, state }, index) => ({ identifier: String(index + 1), reference: profileId, state })),
    },
  ]
  const available = groups.filter(({ rows }) => rows.length > 0)
  const group = available.find(({ id }) => id === groupId) ?? available[0]
  if (!group) return null

  const offset = page * IMPORT_REPORT_PAGE_SIZE
  return (
    <Stack spacing={2} component="section">
      <Typography component="h3" variant="subtitle1">{t('Related import details')}</Typography>
      <TextField
        select
        size="small"
        label={t('Report section')}
        value={group.id}
        onChange={(event) => {
          setGroupId(event.target.value)
          setPage(0)
        }}
        sx={{ maxWidth: 384 }}
      >
        {available.map(({ id, label, rows }) => (
          <MenuItem key={id} value={id}>{t('{{label}} · {{count}}', { label: t(label), count: rows.length })}</MenuItem>
        ))}
      </TextField>
      <TableContainer>
        <Table size="small" aria-label={t(group.label)}>
          <TableHead>
            <TableRow>
              <TableCell>{t('Report identifier')}</TableCell>
              <TableCell>{t('Report reference')}</TableCell>
              <TableCell>{t('Import status')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {group.rows.slice(offset, offset + IMPORT_REPORT_PAGE_SIZE).map(({ identifier, reference, state, reason }, index) => {
              const key = state === 'imported' ? 'Import item completed' : importStatusKey(state)
              return (
                <TableRow key={offset + index}>
                  <TableCell sx={{ overflowWrap: 'anywhere' }}>{identifier}</TableCell>
                  <TableCell sx={{ overflowWrap: 'anywhere' }}>{reference ?? '—'}</TableCell>
                  <TableCell>
                    <Typography variant="body2">{key ? t(key) : state}</Typography>
                    {reason && <Typography variant="caption" color="text.secondary">{reason}</Typography>}
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </TableContainer>
      {group.rows.length > IMPORT_REPORT_PAGE_SIZE && (
        <TablePagination
          component="div"
          count={group.rows.length}
          rowsPerPage={IMPORT_REPORT_PAGE_SIZE}
          rowsPerPageOptions={[]}
          page={page}
          onPageChange={(_, next) => setPage(next)}
          labelDisplayedRows={({ from, to, count }) => t('{{from}}–{{to}} of {{count}}', { from, to, count })}
          getItemAriaLabel={(type) => t(importPaginationLabels[type])}
        />
      )}
    </Stack>
  )
}
