import { useEffect, useId, useRef, useState } from 'react'
import {
  Alert, Button, Checkbox, CircularProgress, Dialog, DialogActions, DialogContent, DialogTitle, Stack,
  Table, TableBody, TableCell, TableContainer, TableHead, TablePagination, TableRow, Typography,
} from '../../ui'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { MessageKey } from '../../i18n/messages'
import { scheduleAbortableLoad } from '../../utils/scheduleAbortableLoad'
import {
  getReusableSectionUsage, refreshReusableSection, ReusableSectionConflict,
  type RefreshReusableSectionResult, type ReusableSection, type ReusableSectionUsage, type ReusableSectionUsageCollection,
} from './catalogApi'

const pageSize = 50
const resultLabels: Record<RefreshReusableSectionResult['status'], MessageKey> = {
  updated: 'Draft updated',
  current: 'Already current',
  conflict: 'Revision conflict',
  missing: 'Draft missing',
  unlinked: 'Link removed',
}
const paginationLabels = {
  first: 'First usage page',
  last: 'Last usage page',
  next: 'Next usage page',
  previous: 'Previous usage page',
} as const

interface SectionUsageDialogProps {
  section: ReusableSection
  editingPageId: string
  onClose: () => void
  onPagesUpdated: (ids: string[], signal: AbortSignal) => Promise<void>
}

export function SectionUsageDialog({ section, editingPageId, onClose, onPagesUpdated }: SectionUsageDialogProps) {
  const { t } = useAdminLocale()
  const titleId = useId()
  const [page, setPage] = useState(0)
  const [reload, setReload] = useState(0)
  const [usage, setUsage] = useState<ReusableSectionUsageCollection | null>(null)
  const [loading, setLoading] = useState(true)
  const [applying, setApplying] = useState(false)
  const [failure, setFailure] = useState<MessageKey | null>(null)
  const [unrefreshedPages, setUnrefreshedPages] = useState<string[]>([])
  const [needsReload, setNeedsReload] = useState(false)
  const [selected, setSelected] = useState<string[]>([])
  const [review, setReview] = useState<{ section: ReusableSection; pages: ReusableSectionUsage[] } | null>(null)
  const [outcomes, setOutcomes] = useState<{ result: RefreshReusableSectionResult; title: string }[]>([])
  const request = useRef<AbortController | null>(null)

  useEffect(() => scheduleAbortableLoad((signal) => {
    getReusableSectionUsage(section.id, pageSize, page * pageSize, signal)
      .then((latest) => {
        if (signal.aborted) return
        const lastPage = Math.max(0, Math.ceil(latest.total / pageSize) - 1)
        if (page > lastPage) {
          setPage(lastPage)
          return
        }
        setUsage(latest)
        setLoading(false)
      })
      .catch(() => {
        if (signal.aborted) return
        setFailure('Could not load section usage.')
        setNeedsReload(true)
        setLoading(false)
      })
  }), [section.id, page, reload])

  useEffect(() => () => request.current?.abort(), [])

  function load(nextPage = page) {
    setLoading(true)
    setFailure(null)
    setNeedsReload(false)
    setSelected([])
    setReview(null)
    if (nextPage === page) setReload((current) => current + 1)
    else setPage(nextPage)
  }

  function isOutdated(row: ReusableSectionUsage, sourceVersion: number): boolean {
    return row.placements.some((link) => link.version < sourceVersion)
  }

  const targets = usage?.items.filter((row) => row.pageId !== editingPageId && isOutdated(row, usage.section.version)) ?? []
  const selectedRows = targets.filter((row) => selected.includes(row.pageId))
  const selectionDisabled = loading || applying || needsReload || review !== null

  async function run(action: (signal: AbortSignal) => Promise<void>) {
    if (request.current) return
    const controller = new AbortController()
    request.current = controller
    setApplying(true)
    try {
      await action(controller.signal)
    } finally {
      request.current = null
      if (!controller.signal.aborted) setApplying(false)
    }
  }

  async function reloadCachedPages(ids: string[], signal: AbortSignal) {
    if (ids.length === 0) return
    try {
      await onPagesUpdated(ids, signal)
      if (!signal.aborted) setUnrefreshedPages([])
    } catch {
      if (!signal.aborted) setUnrefreshedPages(ids)
    }
  }

  function applyRefresh() {
    if (!review) return
    return run(async (signal) => {
      setFailure(null)
      setOutcomes([])
      try {
        const results = await refreshReusableSection(review.section.id, {
          sectionVersion: review.section.version,
          pages: review.pages.map(({ pageId, version }) => ({ pageId, version })),
        }, signal)
        if (signal.aborted) return

        setOutcomes(results.map((result) => ({
          result,
          title: review.pages.find(({ pageId }) => pageId === result.pageId)?.title ?? result.pageId,
        })))
        const reviewedIds = results.map(({ pageId }) => pageId)
        await reloadCachedPages([...new Set([...unrefreshedPages, ...reviewedIds])], signal)
        if (!signal.aborted) load()
      } catch (reason) {
        if (signal.aborted) return
        setFailure(reason instanceof ReusableSectionConflict
          ? 'The library version changed. Reload usage and review the new version.'
          : 'Could not refresh the selected drafts. Reload usage before retrying.')
        setReview(null)
        setNeedsReload(true)
      }
    })
  }

  const title = t('Section usage: {{name}}', { name: usage?.section.name ?? section.name })

  return (
    <Dialog open onClose={() => { if (!applying) onClose() }} fullWidth maxWidth="md" aria-labelledby={titleId}>
      <DialogTitle id={titleId}>{title}</DialogTitle>
      <DialogContent>
        <Stack spacing={2}>
          {failure && <Alert severity="error">{t(failure)}</Alert>}
          {unrefreshedPages.length > 0 && (
            <Alert severity="warning" action={
              <Button size="small" disabled={applying} onClick={() => void run((signal) => reloadCachedPages(unrefreshedPages, signal))}>
                {t('Refresh page list')}
              </Button>
            }>
              {t('The page list could not be refreshed after the batch.')}
            </Alert>
          )}
          {loading && <CircularProgress size={24} aria-label={t('Loading section usage')} />}
          {usage && !loading && (
            <>
              <Typography>{t('Library version {{version}}', { version: usage.section.version })}</Typography>
              {!usage.items.length ? <Typography color="text.secondary">{t('No drafts use this section.')}</Typography> : (
                <>
                  <Typography color="text.secondary" variant="body2">{t('The open draft can be updated individually in the editor. Select drafts on this page to refresh them together.')}</Typography>
                  <TableContainer>
                    <Table size="small" aria-label={t('Section usages')}>
                      <TableHead>
                        <TableRow>
                          <TableCell padding="checkbox">
                            <Checkbox
                              checked={targets.length > 0 && selectedRows.length === targets.length}
                              indeterminate={selectedRows.length > 0 && selectedRows.length < targets.length}
                              disabled={selectionDisabled || targets.length === 0}
                              slotProps={{ input: { 'aria-label': t('Select all eligible drafts on this page') } }}
                              onChange={(_, checked) => setSelected(checked ? targets.map(({ pageId }) => pageId) : [])}
                            />
                          </TableCell>
                          <TableCell>{t('Draft page')}</TableCell>
                          <TableCell>{t('Content language')}</TableCell>
                          <TableCell>{t('Draft revision')}</TableCell>
                          <TableCell>{t('Linked placements')}</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {usage.items.map((row) => {
                          const currentPage = row.pageId === editingPageId
                          const outdated = isOutdated(row, usage.section.version)
                          return (
                            <TableRow key={row.pageId}>
                              <TableCell padding="checkbox">
                                <Checkbox
                                  checked={selected.includes(row.pageId)}
                                  disabled={selectionDisabled || currentPage || !outdated}
                                  slotProps={{ input: { 'aria-label': t('Select draft “{{title}}”', { title: row.title }) } }}
                                  onChange={(_, checked) => setSelected((current) => checked ? [...current, row.pageId] : current.filter((id) => id !== row.pageId))}
                                />
                              </TableCell>
                              <TableCell scope="row" component="th">
                                <Typography variant="body2">{row.title}</Typography>
                                <Typography variant="caption" color="text.secondary">{row.slug}</Typography>
                                {currentPage && <Typography variant="caption" sx={{ display: 'block' }}>{t('Open in editor')}</Typography>}
                              </TableCell>
                              <TableCell>{row.locale}</TableCell>
                              <TableCell>{row.version}</TableCell>
                              <TableCell>
                                {row.placements.map((link) => (
                                  <Typography key={link.blockIds[0]} variant="body2">
                                    {t('Version {{version}} · {{count}} blocks', { version: link.version, count: link.blockIds.length })}
                                  </Typography>
                                ))}
                              </TableCell>
                            </TableRow>
                          )
                        })}
                      </TableBody>
                    </Table>
                  </TableContainer>
                  {usage.total > pageSize && (
                    <TablePagination
                      component="div"
                      count={usage.total}
                      rowsPerPage={pageSize}
                      rowsPerPageOptions={[]}
                      page={page}
                      disabled={loading || applying || review !== null}
                      onPageChange={(_, next) => load(next)}
                      labelDisplayedRows={({ from, to, count }) => t('{{from}}–{{to}} of {{count}}', { from, to, count })}
                      getItemAriaLabel={(kind) => t(paginationLabels[kind])}
                    />
                  )}
                </>
              )}
            </>
          )}
          {review && (
            <Alert severity="warning">
              {t('Apply library version {{version}} ({{blocks}} blocks) to {{count}} selected drafts?', {
                version: review.section.version, blocks: review.section.blocks.length, count: review.pages.length,
              })}
              <Typography variant="body2">{t('This creates draft revisions. Published pages stay unchanged.')}</Typography>
              <Stack component="ul" sx={{ my: 0.5, pl: 3 }}>
                {review.pages.map((row) => {
                  const outdated = row.placements.filter((link) => link.version < review.section.version)
                  const oldBlocks = outdated.reduce((sum, link) => sum + link.blockIds.length, 0)
                  return (
                    <li key={row.pageId}>
                      <Typography variant="body2">
                        {t('{{title}} · {{locale}} · draft revision {{revision}}', { title: row.title, locale: row.locale, revision: row.version })}
                      </Typography>
                      <Typography variant="caption">
                        {t('Placements to update: {{placements}} · Blocks: {{before}} → {{after}} · Placements to keep: {{kept}}', {
                          placements: outdated.length, before: oldBlocks,
                          after: outdated.length * review.section.blocks.length, kept: row.placements.length - outdated.length,
                        })}
                      </Typography>
                    </li>
                  )
                })}
              </Stack>
            </Alert>
          )}
          {outcomes.length > 0 && (
            <Stack spacing={1}>
              <Typography variant="subtitle2">{t('Section refresh results')}</Typography>
              <TableContainer>
                <Table size="small" aria-label={t('Section refresh results')}>
                  <TableHead><TableRow><TableCell>{t('Draft page')}</TableCell><TableCell>{t('Result')}</TableCell><TableCell>{t('Draft revision')}</TableCell></TableRow></TableHead>
                  <TableBody>
                    {outcomes.map(({ result, title: pageTitle }) => (
                      <TableRow key={result.pageId}>
                        <TableCell scope="row" component="th">{pageTitle}</TableCell>
                        <TableCell>{t(resultLabels[result.status])}</TableCell>
                        <TableCell>{result.version ?? '—'}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
              {outcomes.some(({ result }) => result.status === 'conflict') && (
                <Typography variant="body2">{t('Reload usage and review drafts with conflicts before retrying.')}</Typography>
              )}
            </Stack>
          )}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ flexWrap: 'wrap' }}>
        <Button disabled={loading || applying} onClick={() => load()}>{t('Reload section usage')}</Button>
        {review ? (
          <>
            <Button disabled={applying} onClick={() => setReview(null)}>{t('Back to selection')}</Button>
            <Button variant="contained" disabled={applying} onClick={() => void applyRefresh()}>{t('Refresh selected drafts')}</Button>
          </>
        ) : (
          <Button
            variant="contained"
            disabled={!usage || selectionDisabled || selectedRows.length === 0}
            onClick={() => { if (usage) setReview({ section: usage.section, pages: selectedRows }) }}
          >
            {t('Review selected drafts')}
          </Button>
        )}
        <Button disabled={applying} onClick={onClose}>{t('Close')}</Button>
      </DialogActions>
    </Dialog>
  )
}
