import { useEffect, useState } from 'react'
import { Alert, Button, Chip, Stack, TextField, Typography } from '../../ui'
import {
  cancelPublicationSchedule,
  getPublicationSchedule,
  type PublicationSchedule,
  type PublicationTargetType,
  putPublicationSchedule,
} from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { MessageKey } from '../../i18n/messages'
import { scheduleAbortableLoad } from '../../utils/scheduleAbortableLoad'

interface PublicationSchedulerProps {
  target: PublicationTargetType
  targetId: string
  sourceVersion: number
  dirty: boolean
}

export function PublicationScheduler({ target, targetId, sourceVersion, dirty }: PublicationSchedulerProps) {
  const { errorMessage, formatDateTime, t } = useAdminLocale()
  const [schedule, setSchedule] = useState<PublicationSchedule | null>(null)
  const [scheduledAt, setScheduledAt] = useState(defaultLocalDateTime)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<{ reason?: unknown; message: MessageKey } | null>(null)

  useEffect(() => scheduleAbortableLoad((signal) => {
    setLoading(true)
    setError(null)
    setSchedule(null)
    setScheduledAt(defaultLocalDateTime())
    getPublicationSchedule(target, targetId, signal)
      .then((current) => {
        if (signal.aborted) return
        setSchedule(current)
        if (current?.status === 'pending') setScheduledAt(toLocalDateTime(current.scheduledAt))
      })
      .catch((reason: unknown) => {
        if (!signal.aborted) setError({ reason, message: 'Could not load the publication schedule.' })
      })
      .finally(() => { if (!signal.aborted) setLoading(false) })
  }), [target, targetId])

  async function plan() {
    const parsed = new Date(scheduledAt)
    if (!scheduledAt || Number.isNaN(parsed.getTime()) || parsed <= new Date()) {
      setError({ message: 'Choose a future publication date and time.' })
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      setSchedule(await putPublicationSchedule(target, targetId, sourceVersion, parsed.toISOString()))
    } catch (reason) {
      setError({ reason, message: 'Could not schedule publication.' })
    } finally {
      setSubmitting(false)
    }
  }

  async function cancel() {
    setSubmitting(true)
    setError(null)
    try {
      setSchedule(await cancelPublicationSchedule(target, targetId))
    } catch (reason) {
      setError({ reason, message: 'Could not cancel the publication schedule.' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Stack spacing={1.5} sx={{ p: 2, border: 1, borderColor: 'divider', borderRadius: 1 }}>
      <Stack direction={{ xs: 'column', md: 'row' }} sx={{ justifyContent: 'space-between', gap: 1 }}>
        <div>
          <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>{t('Scheduled publication')}</Typography>
          <Typography variant="body2" color="text.secondary">{t('The exact saved version will be published by the scheduler.')}</Typography>
        </div>
        {schedule && <Chip label={t(schedule.status)} color={schedule.status === 'failed' ? 'error' : schedule.status === 'pending' ? 'warning' : 'default'} size="small" />}
      </Stack>
      {error && <Alert severity="error">{errorMessage(error.reason, error.message)}</Alert>}
      {schedule?.failureReason && <Alert severity="error">{errorMessage(new Error(schedule.failureReason), 'Scheduled publication failed.')}</Alert>}
      {schedule?.status === 'pending' && (
        <Alert severity="info">{t('Version {{version}} is scheduled for {{date}}.', { version: schedule.sourceVersion, date: formatDateTime(schedule.scheduledAt) })}</Alert>
      )}
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
        <TextField
          type="datetime-local"
          label={t('Publish at')}
          value={scheduledAt}
          onChange={(event) => setScheduledAt(event.target.value)}
          disabled={loading || submitting}
          slotProps={{ inputLabel: { shrink: true } }}
          fullWidth
        />
        <Button type="button" variant="outlined" disabled={loading || submitting || dirty} onClick={() => void plan()} sx={{ minWidth: 170 }}>
          {submitting ? t('Working…') : schedule?.status === 'pending' ? t('Reschedule') : t('Schedule')}
        </Button>
        {schedule?.status === 'pending' && (
          <Button type="button" color="error" disabled={submitting} onClick={() => void cancel()}>{t('Cancel')}</Button>
        )}
      </Stack>
      {dirty && <Typography variant="caption" color="warning.main">{t('Save the draft before scheduling it.')}</Typography>}
    </Stack>
  )
}

function defaultLocalDateTime(): string {
  return toLocalDateTime(new Date(Date.now() + 60 * 60 * 1000).toISOString())
}

function toLocalDateTime(value: string): string {
  const date = new Date(value)
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
  return local.toISOString().slice(0, 16)
}
