import { useEffect, useId, useRef, useState } from 'react'
import {
  Alert, Button, Chip, CircularProgress, Dialog, DialogActions, DialogContent, DialogTitle, MenuItem, Stack, Table,
  TableBody, TableCell, TableContainer, TableHead, TableRow, TextField, Typography,
} from '../../ui'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { MessageKey } from '../../i18n/messages'
import { scheduleAbortableLoad } from '../../utils/scheduleAbortableLoad'
import { decidePageReview, getPageReviews, type DecidePageReview, type PageReview } from './reviewsApi'

const statusLabels = {
  pending: 'In review',
  approved: 'Approved',
  changes_requested: 'Changes requested',
} as const satisfies Record<PageReview['status'], MessageKey>
const statuses = Object.keys(statusLabels) as PageReview['status'][]
const limit = 50
const commentLimit = 1000

interface PageReviewDialogProps {
  pageId?: string
  isOwner: boolean
  onClose: () => void
}

export function PageReviewDialog({ pageId, isOwner, onClose }: PageReviewDialogProps) {
  const { formatDateTime, t, locale } = useAdminLocale()
  const titleId = useId()
  const [status, setStatus] = useState<PageReview['status'] | ''>(pageId ? '' : 'pending')
  const [items, setItems] = useState<PageReview[]>([])
  const [loading, setLoading] = useState(true)
  const [reload, setReload] = useState(0)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<PageReview | null>(null)
  const [selected, setSelected] = useState<PageReview | null>(null)
  const [comment, setComment] = useState('')
  const [deciding, setDeciding] = useState(false)
  const request = useRef<AbortController | null>(null)

  useEffect(() => scheduleAbortableLoad((signal) => {
    getPageReviews({ status: status || undefined, pageId, limit }, signal)
      .then((results) => {
        if (signal.aborted) return
        setItems(results)
        setLoading(false)
      })
      .catch(() => {
        if (signal.aborted) return
        setItems([])
        setError(t('Could not load page reviews.'))
        setLoading(false)
      })
  }), [pageId, status, reload])

  useEffect(() => () => request.current?.abort(), [])

  function load(nextStatus = status) {
    setLoading(true)
    setError(null)
    setSelected(null)
    setComment('')
    if (nextStatus === status) setReload((current) => current + 1)
    else setStatus(nextStatus)
  }

  async function decide(review: PageReview, decision: DecidePageReview) {
    if (!isOwner || review.status !== 'pending' || request.current) return
    const controller = new AbortController()
    request.current = controller
    setDeciding(true)
    setError(null)
    setNotice(null)
    try {
      const updated = await decidePageReview(review.id, decision, controller.signal)
      if (controller.signal.aborted) return
      setItems((current) => current.flatMap((item) => {
        if (item.id !== updated.id) return [item]
        return !status || updated.status === status ? [updated] : []
      }))
      setSelected(null)
      setComment('')
      setNotice(updated)
    } catch (reason) {
      if (controller.signal.aborted) return
      setError(locale === 'en' && reason instanceof Error ? reason.message : t('Could not save the review decision.'))
    } finally {
      request.current = null
      if (!controller.signal.aborted) setDeciding(false)
    }
  }

  function openChangesRequest(review: PageReview) {
    setSelected(review)
    setComment('')
    setError(null)
  }

  return (
    <Dialog open fullWidth maxWidth="md" aria-labelledby={titleId} onClose={() => { if (!deciding) onClose() }}>
      <DialogTitle id={titleId}>{pageId ? t('Review history') : t('Review queue')}</DialogTitle>
      <DialogContent>
        <Stack spacing={2} sx={{ pt: 1 }}>
          <TextField
            select label={t('Review status')} value={status} disabled={deciding}
            onChange={(event) => load(event.target.value as PageReview['status'] | '')}
            sx={{ alignSelf: 'flex-start', minWidth: 180 }}
          >
            <MenuItem value="">{t('All statuses')}</MenuItem>
            {statuses.map((value) => <MenuItem key={value} value={value}>{t(statusLabels[value])}</MenuItem>)}
          </TextField>
          {error && <Alert severity="error">{error}</Alert>}
          {notice && <Alert severity="success">{t('Review decision saved for revision {{revision}}: {{status}}.', {
            revision: notice.revision, status: t(statusLabels[notice.status]),
          })}</Alert>}
          {loading ? <CircularProgress size={24} aria-label={t('Loading page reviews')} /> : items.length === 0 ? (
            !error && <Typography color="text.secondary">{t('No reviews match these filters.')}</Typography>
          ) : (
            <TableContainer>
              <Table size="small" aria-label={pageId ? t('Review history') : t('Review queue')}>
                <TableHead>
                  <TableRow>
                    <TableCell>{t('Draft page')}</TableCell>
                    <TableCell>{t('Draft revision')}</TableCell>
                    <TableCell>{t('Review status')}</TableCell>
                    <TableCell>{t('Submitted for review')}</TableCell>
                    <TableCell>{t('Review decision')}</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {items.map((review) => (
                    <TableRow key={review.id}>
                      <TableCell component="th" scope="row">
                        <Typography variant="body2">{review.pageTitle}</Typography>
                        <Typography variant="caption" color="text.secondary">{review.locale} · {review.pageSlug}</Typography>
                      </TableCell>
                      <TableCell>{review.revision}</TableCell>
                      <TableCell><Chip size="small" label={t(statusLabels[review.status])} /></TableCell>
                      <TableCell>{formatDateTime(review.submittedAt)}</TableCell>
                      <TableCell>
                        {review.decidedAt && <Typography variant="body2">{formatDateTime(review.decidedAt)}</Typography>}
                        {review.comment && <Typography variant="body2" sx={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' }}>{review.comment}</Typography>}
                        {isOwner && review.status === 'pending' && (
                          <Stack direction="row" sx={{ gap: 1, flexWrap: 'wrap' }}>
                            <Button size="small" disabled={deciding || selected !== null} onClick={() => void decide(review, { status: 'approved' })}>
                              {t('Approve review')}
                            </Button>
                            <Button size="small" disabled={deciding || selected !== null} onClick={() => openChangesRequest(review)}>
                              {t('Request changes')}
                            </Button>
                          </Stack>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
          {items.length === limit && <Typography variant="caption" color="text.secondary">{t('Showing the latest {{count}} reviews.', { count: limit })}</Typography>}
          {selected && (
            <Stack spacing={1} role="group" aria-label={t('Request changes')}>
              <Typography variant="subtitle2">{t('Revision {{revision}} · {{title}}', { revision: selected.revision, title: selected.pageTitle })}</Typography>
              <TextField
                label={t('Review decision comment')} value={comment} disabled={deciding}
                multiline minRows={2} onChange={(event) => setComment(event.target.value)}
                slotProps={{ htmlInput: { maxLength: commentLimit, 'aria-required': true } }}
              />
              <Stack direction="row" spacing={1}>
                <Button disabled={deciding} onClick={() => setSelected(null)}>{t('Cancel')}</Button>
                <Button
                  variant="contained"
                  disabled={deciding || !comment.trim() || comment.trim().length > commentLimit}
                  onClick={() => void decide(selected, { status: 'changes_requested', comment: comment.trim() })}
                >
                  {t('Confirm review decision')}
                </Button>
              </Stack>
            </Stack>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button disabled={loading || deciding} onClick={() => load()}>{t('Reload reviews')}</Button>
        <Button disabled={deciding} onClick={onClose}>{t('Close')}</Button>
      </DialogActions>
    </Dialog>
  )
}
