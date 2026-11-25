import { useEffect, useRef, useState, type Dispatch, type SetStateAction } from 'react'
import { Alert, Button, Card, CardContent, Chip, Stack, TextField, Typography } from '../../ui'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { PageBlock, PageSectionLink } from './api'
import {
  createReusableSection,
  deleteReusableSection,
  getReusableSections,
  ReusableSectionConflict,
  updateReusableSection,
  type ReusableSection,
} from './catalogApi'
import { copySectionBlocks, insertLinkedSection, refreshLinkedSection, richTextPreview, type PageComposition } from './pageBuilder'

interface PageSectionsPanelProps {
  page: PageComposition
  sections: ReusableSection[]
  setSections: Dispatch<SetStateAction<ReusableSection[]>>
  onChange: (composition: PageComposition) => void
  disabled: boolean
}

export function PageSectionsPanel({ page, sections, setSections, onChange, disabled }: PageSectionsPanelProps) {
  const { locale, t } = useAdminLocale()
  const [name, setName] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [status, setStatus] = useState<string | null>(null)
  const [pending, setPending] = useState<{ link: PageSectionLink; section: ReusableSection } | null>(null)
  const request = useRef<AbortController | null>(null)
  const controlsDisabled = disabled || busy

  useEffect(() => () => request.current?.abort(), [])

  async function run(action: (signal: AbortSignal) => Promise<void>, fallback: string) {
    if (request.current) return
    const controller = new AbortController()
    request.current = controller
    setBusy(true)
    setError(null)
    setStatus(null)
    setPending(null)
    try {
      await action(controller.signal)
    } catch (reason) {
      if (!controller.signal.aborted) {
        setError(locale === 'en' && reason instanceof Error ? reason.message : fallback)
      }
    } finally {
      request.current = null
      if (!controller.signal.aborted) setBusy(false)
    }
  }

  function saveSection() {
    return run(async (signal) => {
      const section = await createReusableSection(name.trim(), page.blocks, signal)
      if (signal.aborted) return
      setSections((current) => [...current, section].sort((left, right) => left.name.localeCompare(right.name)))
      setName('')
      setStatus(t('Reusable section created.'))
    }, t('Could not create reusable section.'))
  }

  function replaceSection(section: ReusableSection) {
    if (!window.confirm(t('Replace this library section with all blocks on the current page?'))) return
    return run(async (signal) => {
      let updated: ReusableSection
      try {
        updated = await updateReusableSection(section, page.blocks, signal)
      } catch (reason) {
        if (!(reason instanceof ReusableSectionConflict)) throw reason
        const latest = await getReusableSections(signal)
        if (!signal.aborted) {
          setSections(latest)
          setError(t('The library section changed. Review the current version and try again.'))
        }
        return
      }
      if (signal.aborted) return
      setSections((current) => current.map((item) => item.id === updated.id ? updated : item))
      setStatus(t('Reusable section updated to version {{version}}.', { version: updated.version }))
    }, t('Could not update reusable section.'))
  }

  function removeSection(section: ReusableSection) {
    return run(async (signal) => {
      await deleteReusableSection(section.id, signal)
      if (!signal.aborted) setSections((current) => current.filter((item) => item.id !== section.id))
    }, t('Could not delete reusable section.'))
  }

  function checkUpdate(link: PageSectionLink) {
    return run(async (signal) => {
      const latest = await getReusableSections(signal)
      if (signal.aborted) return
      setSections(latest)
      const section = latest.find((item) => item.id === link.sectionId)
      if (!section) {
        setStatus(t('This section is no longer in the library. The page keeps its blocks.'))
      } else if (section.version > link.version) {
        setPending({ link, section })
      } else {
        setStatus(t('This placement already uses the latest version.'))
      }
    }, t('Could not check for a section update.'))
  }

  function applyUpdate(link: PageSectionLink, section: ReusableSection) {
    const updated = refreshLinkedSection(page, link, section)
    if (updated !== page) onChange(updated)
    setPending(null)
  }

  return (
    <Card variant="outlined">
      <CardContent>
        <Stack spacing={2}>
          <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>{t('Reusable sections')}</Typography>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1}>
            <TextField size="small" label={t('Save current canvas as')} value={name} disabled={controlsDisabled} onChange={(event) => setName(event.target.value)} />
            <Button variant="outlined" disabled={controlsDisabled || !name.trim() || page.blocks.length === 0} onClick={() => void saveSection()}>
              {t('Save section')}
            </Button>
          </Stack>
          {error && <Alert severity="error">{error}</Alert>}
          {status && <Alert severity="info">{status}</Alert>}
          {sections.map((section) => (
            <Stack
              key={section.id}
              role="group"
              aria-label={section.name}
              direction="row"
              sx={{ alignItems: 'center', flexWrap: 'wrap', gap: 1 }}
            >
              <Chip
                label={t('{{name}} · {{count}} blocks', { name: section.name, count: section.blocks.length })}
                aria-label={t('Insert a copy of {{name}}', { name: section.name })}
                title={t('Insert a copy of {{name}}', { name: section.name })}
                disabled={controlsDisabled}
                onClick={() => onChange({ ...page, blocks: [...page.blocks, ...copySectionBlocks(section.blocks)] })}
                onDelete={() => void removeSection(section)}
              />
              <Typography variant="body2" color="text.secondary">{t('Library version {{version}}', { version: section.version })}</Typography>
              <Button size="small" disabled={controlsDisabled} onClick={() => onChange(insertLinkedSection(page, section))}>
                {t('Insert linked section')}
              </Button>
              <Button size="small" disabled={controlsDisabled || page.blocks.length === 0} onClick={() => void replaceSection(section)}>
                {t('Replace section with page blocks')}
              </Button>
            </Stack>
          ))}
          {page.sectionLinks.map((link) => {
            const source = sections.find((section) => section.id === link.sectionId)
            const label = t('Linked section: {{name}}', { name: source?.name ?? t('Missing library section') })
            return (
              <Stack key={link.blockIds[0]} role="group" aria-label={label} spacing={1}>
                <Typography variant="body2" sx={{ fontWeight: 700 }}>{label}</Typography>
                <Typography variant="body2" color="text.secondary">
                  {source
                    ? t('In this draft: version {{installed}} · In the library: version {{latest}}', { installed: link.version, latest: source.version })
                    : t('In this draft: version {{version}}', { version: link.version })}
                </Typography>
                <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1 }}>
                  <Button size="small" disabled={controlsDisabled} onClick={() => void checkUpdate(link)}>
                    {t('Check section update')}
                  </Button>
                  <Button size="small" disabled={controlsDisabled} onClick={() => onChange({ ...page, sectionLinks: page.sectionLinks.filter((item) => item !== link) })}>
                    {t('Detach section')}
                  </Button>
                </Stack>
                {pending?.link === link && (
                  <Alert severity="warning">
                    {t('Apply version {{version}} and replace {{current}} draft blocks with {{updated}} library blocks?', {
                      version: pending.section.version, current: link.blockIds.length, updated: pending.section.blocks.length,
                    })}
                    <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ my: 1 }}>
                      <SectionBlockSummary label={t('Current draft blocks')} blocks={page.blocks.filter((block) => link.blockIds.includes(block.id))} />
                      <SectionBlockSummary label={t('Library blocks')} blocks={pending.section.blocks} />
                    </Stack>
                    <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1 }}>
                      <Button size="small" disabled={controlsDisabled} onClick={() => applyUpdate(link, pending.section)}>
                        {t('Apply section update')}
                      </Button>
                      <Button size="small" onClick={() => setPending(null)}>{t('Cancel')}</Button>
                    </Stack>
                  </Alert>
                )}
              </Stack>
            )
          })}
        </Stack>
      </CardContent>
    </Card>
  )
}

function SectionBlockSummary({ label, blocks }: { label: string; blocks: PageBlock[] }) {
  const { t } = useAdminLocale()
  return (
    <Stack role="group" aria-label={label} sx={{ minWidth: 0, flex: 1, overflowWrap: 'anywhere' }}>
      <Typography variant="subtitle2">{label}</Typography>
      <Stack component="ul" sx={{ my: 0.5, pl: 3 }}>
        {blocks.map((block) => (
          <Typography key={block.id} component="li" variant="body2">
            {block.type === 'hero' ? block.data.heading : block.type === 'richText'
              ? (richTextPreview(block.data.content).slice(0, 140) || t('Rich text content'))
              : (block.data.alt || t('Image'))}
          </Typography>
        ))}
      </Stack>
    </Stack>
  )
}
