import { useState } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { MessageKey } from '../../i18n/messages'
import {
  Alert, Button, Checkbox, MenuItem, Stack, Table, TableBody, TableCell, TableContainer,
  TableHead, TablePagination, TableRow, TextField, Typography,
} from '../../ui'
import { filterImportItems, type WordPressImportItem } from './filterImportItems'
import type { WordPressImportReport } from './api'
import { IMPORT_REPORT_PAGE_SIZE, importPaginationLabels, importReasonKey, importStatusKey } from './reportPresentation'

const typeLabels = {
  page: 'WordPress pages',
  post: 'WordPress posts',
} as const

const redirectLabels = new Map<string, MessageKey>([
  ['planned', 'Redirect planned'],
  ['registered', 'Redirect registered'],
  ['already_registered', 'Redirect already registered'],
  ['conflict', 'Redirect conflict'],
  ['unsupported', 'Redirect URL is unsupported'],
])

function ItemStatus({ item }: { item: WordPressImportItem }) {
  const { t } = useAdminLocale()
  const status = importStatusKey(item.state)
  const reasonKey = item.reason && importReasonKey(item.reason)
  const redirect = item.redirect ? redirectLabels.get(item.redirect) : undefined
  return (
    <Stack spacing={0.25}>
      <Typography variant="body2">{status ? t(status) : item.state}</Typography>
      {item.reason && <Typography variant="caption" color="text.secondary">{reasonKey ? t(reasonKey) : item.reason}</Typography>}
      {item.images && item.images.missingInline > 0 && (
        <Typography variant="caption" color="warning.dark">{t('Missing inline images: {{count}}', { count: item.images.missingInline })}</Typography>
      )}
      {item.images?.featured === 'missing' && (
        <Typography variant="caption" color="warning.dark">{t('Featured image is missing')}</Typography>
      )}
      {item.author === 'linked' && <Typography variant="caption" color="text.secondary">{t('Author profile linked')}</Typography>}
      {item.author === 'missing' && <Typography variant="caption" color="warning.dark">{t('Author profile is missing')}</Typography>}
      {redirect && (
        <Typography variant="caption" color={item.redirect === 'conflict' || item.redirect === 'unsupported' ? 'warning.dark' : 'text.secondary'}>
          {t(redirect)}
        </Typography>
      )}
    </Stack>
  )
}

function DraftLink({ draft }: { draft: NonNullable<WordPressImportItem['draft']> }) {
  const { t } = useAdminLocale()
  const query = new URLSearchParams({ draft: draft.id })
  if (draft.kind === 'entry') query.set('type', draft.contentTypeId)

  return (
    <Button
      component="a"
      href={`/admin/${draft.kind === 'page' ? 'pages' : 'entries'}?${query}`}
      target="_blank"
      rel="noopener"
      size="small"
      sx={{ alignSelf: 'flex-start', px: 0 }}
    >
      {t('Open draft in a new tab')}
    </Button>
  )
}

export interface ImportItemSelection {
  excludedIds: ReadonlySet<string>
  plannedCount: number
  disabled: boolean
  onChange: (ids: readonly string[], include: boolean) => void
}

export function WordPressImportItems({ items, input, selection }: {
  items: readonly WordPressImportItem[]
  input: WordPressImportReport['input']
  selection?: ImportItemSelection
}) {
  const { t } = useAdminLocale()
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [state, setState] = useState('')
  const [page, setPage] = useState(0)

  if (items.length === 0) {
    return <Alert severity="info">{t(input === 'rest'
      ? 'The REST source contains no pages or posts to display.'
      : 'The export contains no pages or posts to display.')}</Alert>
  }

  const states = [...new Set(items.map((item) => item.state))]
  const matches = filterImportItems(items, { search, type, state })
  const matchingPlannedIds = selection ? matches.filter((item) => item.state === 'planned').map((item) => item.id) : []
  const offset = page * IMPORT_REPORT_PAGE_SIZE

  return (
    <Stack spacing={2}>
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
        <TextField
          size="small"
          label={t('Search by title, ID or path')}
          value={search}
          sx={{ flex: 1 }}
          onChange={(event) => {
            setSearch(event.target.value)
            setPage(0)
          }}
        />
        <TextField
          select
          size="small"
          label={t('Item type')}
          value={type}
          sx={{ minWidth: 160 }}
          onChange={(event) => {
            setType(event.target.value)
            setPage(0)
          }}
        >
          <MenuItem value="">{t('All item types')}</MenuItem>
          <MenuItem value="page">{t(typeLabels.page)}</MenuItem>
          <MenuItem value="post">{t(typeLabels.post)}</MenuItem>
        </TextField>
        <TextField
          select
          size="small"
          label={t('Import status')}
          value={state}
          sx={{ minWidth: 200 }}
          onChange={(event) => {
            setState(event.target.value)
            setPage(0)
          }}
        >
          <MenuItem value="">{t('All import statuses')}</MenuItem>
          {states.map((value) => {
            const key = importStatusKey(value)
            return <MenuItem key={value} value={value}>{key ? t(key) : value}</MenuItem>
          })}
        </TextField>
      </Stack>
      {selection && (
        <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap', alignItems: 'center' }}>
          <Typography role="status" variant="body2" color="text.secondary" sx={{ mr: 1 }}>
            {t('{{selected}} of {{total}} importable items selected', {
              selected: selection.plannedCount - selection.excludedIds.size,
              total: selection.plannedCount,
            })}
          </Typography>
          <Button
            size="small"
            disabled={selection.disabled || !matchingPlannedIds.some((id) => selection.excludedIds.has(id))}
            onClick={() => selection.onChange(matchingPlannedIds, true)}
          >
            {t('Select all matching items')}
          </Button>
          <Button
            size="small"
            disabled={selection.disabled || !matchingPlannedIds.some((id) => !selection.excludedIds.has(id))}
            onClick={() => selection.onChange(matchingPlannedIds, false)}
          >
            {t('Clear matching selection')}
          </Button>
        </Stack>
      )}
      {matches.length === 0 ? (
        <Alert severity="info">{t('No items match the selected filters.')}</Alert>
      ) : (
        <>
          <TableContainer>
            <Table size="small" aria-label={t('WordPress import report')}>
              <TableHead>
                <TableRow>
                  {selection && <TableCell padding="checkbox">{t('Include in import')}</TableCell>}
                  <TableCell>{t('WordPress item')}</TableCell>
                  <TableCell>{t('Source path')}</TableCell>
                  <TableCell>{t('Draft path')}</TableCell>
                  <TableCell>{t('Import status')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {matches.slice(offset, offset + IMPORT_REPORT_PAGE_SIZE).map((item) => {
                  const itemLabel = t(item.type === 'page' ? 'WordPress page #{{id}}' : 'WordPress post #{{id}}', { id: item.id })
                  return (
                    <TableRow key={item.id}>
                      {selection && (
                        <TableCell padding="checkbox">
                          {item.state === 'planned' && (
                            <Checkbox
                              size="small"
                              checked={!selection.excludedIds.has(item.id)}
                              disabled={selection.disabled}
                              onChange={(event) => selection.onChange([item.id], event.target.checked)}
                              slotProps={{ input: { 'aria-label': t('Include {{item}} in the import', { item: itemLabel }) } }}
                            />
                          )}
                        </TableCell>
                      )}
                      <TableCell component="th" scope="row" sx={{ overflowWrap: 'anywhere' }}>
                        {item.title && <Typography variant="body2">{item.title}</Typography>}
                        <Typography variant={item.title ? 'caption' : 'body2'} color={item.title ? 'text.secondary' : 'text.primary'}>
                          {itemLabel}
                        </Typography>
                      </TableCell>
                      <TableCell sx={{ overflowWrap: 'anywhere' }}>{item.sourcePath ?? '—'}</TableCell>
                      <TableCell sx={{ overflowWrap: 'anywhere' }}>{item.targetPath ?? '—'}</TableCell>
                      <TableCell>
                        <Stack spacing={0.5}>
                          <ItemStatus item={item} />
                          {item.draft && <DraftLink draft={item.draft} />}
                        </Stack>
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </TableContainer>
          {matches.length > IMPORT_REPORT_PAGE_SIZE && (
            <TablePagination
              component="div"
              count={matches.length}
              rowsPerPage={IMPORT_REPORT_PAGE_SIZE}
              rowsPerPageOptions={[]}
              page={page}
              onPageChange={(_, next) => setPage(next)}
              labelDisplayedRows={({ from, to, count }) => t('{{from}}–{{to}} of {{count}}', { from, to, count })}
              getItemAriaLabel={(kind) => t(importPaginationLabels[kind])}
            />
          )}
        </>
      )}
    </Stack>
  )
}
