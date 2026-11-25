import { useEffect, useRef, useState } from 'react'
import { Alert, Button, Stack } from '../../ui'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import { PageReviewDialog } from './PageReviewDialog'
import { submitPageReview } from './reviewsApi'

interface PageReviewActionsProps {
  pageId: string
  revision: number
  isOwner: boolean
  disabled: boolean
}

export function PageReviewActions({ pageId, revision, isOwner, disabled }: PageReviewActionsProps) {
  const { locale, t } = useAdminLocale()
  const [historyOpen, setHistoryOpen] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [submittedRevision, setSubmittedRevision] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const request = useRef<AbortController | null>(null)

  useEffect(() => () => request.current?.abort(), [])

  async function submit() {
    if (disabled || request.current) return
    const controller = new AbortController()
    request.current = controller
    setSubmitting(true)
    setError(null)
    setSubmittedRevision(null)
    try {
      const review = await submitPageReview(pageId, revision, controller.signal)
      if (controller.signal.aborted) return
      setSubmittedRevision(review.revision)
      setHistoryOpen(true)
    } catch (reason) {
      if (controller.signal.aborted) return
      setError(locale === 'en' && reason instanceof Error ? reason.message : t('Could not submit the revision for review.'))
    } finally {
      request.current = null
      if (!controller.signal.aborted) setSubmitting(false)
    }
  }

  return (
    <Stack spacing={1}>
      <Stack direction="row" sx={{ gap: 1, flexWrap: 'wrap' }}>
        <Button variant="outlined" disabled={disabled || submitting} onClick={() => void submit()}>
          {t('Submit for review')}
        </Button>
        <Button disabled={submitting} onClick={() => setHistoryOpen(true)}>{t('Review history')}</Button>
      </Stack>
      {error && <Alert severity="error">{error}</Alert>}
      {submittedRevision !== null && <Alert severity="success">{t('Revision {{revision}} was submitted for review.', { revision: submittedRevision })}</Alert>}
      {historyOpen && <PageReviewDialog pageId={pageId} isOwner={isOwner} onClose={() => setHistoryOpen(false)} />}
    </Stack>
  )
}
