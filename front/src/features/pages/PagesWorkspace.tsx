import { type FormEvent, useEffect, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Divider,
  FormControlLabel,
  MenuItem,
  Stack,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '../../ui'
import { type MediaAsset } from '../media/api'
import type { NavigationMenu } from '../navigation/api'
import { getMediaThumbnail } from '../media/MediaLibrary'
import { PublicationScheduler } from '../publishing/PublicationScheduler'
import {
  createPage,
  createPageRedirect,
  deletePageRedirect,
  deletePage,
  getPageRedirects,
  getPageRevisions,
  getPages,
  type PageRevision,
  type PageSummary,
  type PublishedPage,
  type PublishedRedirect,
  publishPage,
  restorePageRevision,
  unpublishPage,
  updatePage,
} from './api'
import { RichTextEditor } from '../richText/RichTextEditor'
import { createUuid } from '../../utils/createUuid'
import { createReusableSection, deleteReusableSection, getReusableSections, getThemes, type ReusableSection, type Theme } from './catalogApi'
import { copySectionBlocks, duplicatePageBlock, movePageBlock } from './pageBuilder'
import { getSiteSettings } from '../settings/api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'

const publicSiteUrl = import.meta.env.VITE_PUBLIC_SITE_URL || window.location.origin

function richTextPreview(content: string): string {
  return content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

interface PagesWorkspaceProps {
  mediaAssets: MediaAsset[]
  navigationMenus: NavigationMenu[]
}

export function PagesWorkspace({ mediaAssets, navigationMenus }: PagesWorkspaceProps) {
  const { formatDateTime, locale: adminLocale, t } = useAdminLocale()
  const [pages, setPages] = useState<PageSummary[]>([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [slug, setSlug] = useState('')
  const [locale, setLocale] = useState('en')
  const [defaultLocale, setDefaultLocale] = useState('en')
  const [translationSourceId, setTranslationSourceId] = useState('')
  const [editing, setEditing] = useState<PageSummary | null>(null)
  const [editingDirty, setEditingDirty] = useState(false)
  const [publication, setPublication] = useState<PublishedPage | null>(null)
  const [redirects, setRedirects] = useState<PublishedRedirect[]>([])
  const [redirectSlug, setRedirectSlug] = useState('')
  const [redirectBusy, setRedirectBusy] = useState(false)
  const [revisions, setRevisions] = useState<PageRevision[]>([])
  const [restoringRevision, setRestoringRevision] = useState<number | null>(null)
  const [themes, setThemes] = useState<Theme[]>([])
  const [sections, setSections] = useState<ReusableSection[]>([])
  const [sectionName, setSectionName] = useState('')
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null)
  const [draggedBlockId, setDraggedBlockId] = useState<string | null>(null)
  const [builderViewport, setBuilderViewport] = useState<'desktop' | 'tablet' | 'mobile'>('desktop')

  useEffect(() => {
    const controller = new AbortController()

    getPages(controller.signal)
      .then(setPages)
      .catch((reason: unknown) => {
        if (reason instanceof DOMException && reason.name === 'AbortError') {
          return
        }

        setError(localizedError(adminLocale, reason, t('Could not load pages.')))
      })
      .finally(() => setLoading(false))

    return () => controller.abort()
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    Promise.all([getThemes(controller.signal), getReusableSections(controller.signal), getSiteSettings(controller.signal)])
      .then(([themeCatalog, sectionCatalog, settings]) => { setThemes(themeCatalog); setSections(sectionCatalog); setLocale(settings.defaultLocale); setDefaultLocale(settings.defaultLocale) })
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(localizedError(adminLocale, reason, t('Could not load page builder catalogs.')))
      })
    return () => controller.abort()
  }, [])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      const source = pages.find((page) => page.id === translationSourceId)
      const page = await createPage(title, slug, locale, source?.translationGroupId)
      setPages((current) => [page, ...current])
      setTitle('')
      setSlug('')
      setLocale(defaultLocale)
      setTranslationSourceId('')
    } catch (reason) {
      setError(localizedError(adminLocale, reason, t('Could not create the page.')))
    } finally {
      setSubmitting(false)
    }
  }

  async function saveRevision(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!editing) return

    setSaving(true)
    setError(null)

    try {
      const saved = await updatePage(editing.id, {
        title: editing.title,
        slug: editing.slug,
        blocks: editing.blocks,
        seo: editing.seo,
        layout: editing.layout,
        locale: editing.locale,
        translationGroupId: editing.translationGroupId,
        version: editing.version,
      })
      setEditing(saved)
      setEditingDirty(false)
      setPages((current) => current.map((page) => page.id === saved.id ? saved : page))
      setRevisions(await getPageRevisions(saved.id))
    } catch (reason) {
      setError(localizedError(adminLocale, reason, t('Could not save the revision.')))
    } finally {
      setSaving(false)
    }
  }

  async function publishCurrentRevision() {
    if (!editing || editingDirty) return

    setPublishing(true)
    setError(null)
    setSuccess(null)

    try {
      const published = await publishPage(editing.id, editing.version)
      setPublication(published)
      try {
        setRedirects(await getPageRedirects(editing.id))
      } catch (reason) {
        setError(localizedError(adminLocale, reason, t('Published, but redirect history could not be refreshed.')))
      }
    } catch (reason) {
      setError(localizedError(adminLocale, reason, t('Could not publish the page.')))
    } finally {
      setPublishing(false)
    }
  }

  async function unpublishCurrentPage() {
    if (!editing || !window.confirm(t('Unpublish this page? Its draft and publication history will be preserved.'))) return

    setPublishing(true)
    setError(null)
    setSuccess(null)
    try {
      await unpublishPage(editing.id)
      setPublication(null)
      setRedirects([])
      setSuccess(t('The page is no longer publicly available.'))
    } catch (reason) {
      setError(localizedError(adminLocale, reason, t('Could not unpublish the page.')))
    } finally {
      setPublishing(false)
    }
  }

  async function deleteCurrentPage() {
    if (!editing || !window.confirm(t('Permanently delete this page draft and all revisions? Published snapshots will be retained.'))) return

    setDeleting(true)
    setError(null)
    setSuccess(null)
    try {
      await deletePage(editing.id)
      setPages((current) => current.filter((page) => page.id !== editing.id))
      setEditing(null)
      setPublication(null)
      setRedirects([])
      setSuccess(t('The page draft was permanently deleted.'))
    } catch (reason) {
      setError(localizedError(adminLocale, reason, t('Could not delete the page.')))
    } finally {
      setDeleting(false)
    }
  }

  function openEditor(page: PageSummary) {
    setEditing(structuredClone(page))
    setEditingDirty(false)
    setPublication(null)
    setSuccess(null)
    setRedirects([])
    setRevisions([])
    setSelectedBlockId(page.blocks[0]?.id ?? null)
    getPageRedirects(page.id)
      .then(setRedirects)
      .catch((reason: unknown) => {
        setError(localizedError(adminLocale, reason, t('Could not load redirect history.')))
      })
    getPageRevisions(page.id)
      .then(setRevisions)
      .catch((reason: unknown) => setError(localizedError(adminLocale, reason, t('Could not load revision history.'))))
  }

  async function restoreRevision(revision: number) {
    if (!editing || editingDirty || !window.confirm(t('Restore revision {{revision}} as a new draft revision?', { revision }))) return
    setRestoringRevision(revision); setError(null); setSuccess(null)
    try {
      const restored = await restorePageRevision(editing.id, revision, editing.version)
      setEditing(restored)
      setPages((current) => current.map((page) => page.id === restored.id ? restored : page))
      setRevisions(await getPageRevisions(restored.id))
      setSuccess(t('Revision {{source}} was restored as revision {{target}}.', { source: revision, target: restored.version }))
    } catch (reason) { setError(localizedError(adminLocale, reason, t('Could not restore the revision.'))) }
    finally { setRestoringRevision(null) }
  }

  async function addManualRedirect() {
    if (!editing || !redirectSlug) return
    setRedirectBusy(true); setError(null)
    try { const redirect = await createPageRedirect(editing.id, redirectSlug); setRedirects((current) => [...current.filter((item) => item.sourceSlug !== redirect.sourceSlug), redirect]); setRedirectSlug('') }
    catch (reason) { setError(localizedError(adminLocale, reason, t('Could not create redirect.'))) }
    finally { setRedirectBusy(false) }
  }

  async function removeManualRedirect(sourceSlug: string) {
    if (!editing) return
    setRedirectBusy(true); setError(null)
    try { await deletePageRedirect(editing.id, sourceSlug); setRedirects((current) => current.filter((item) => item.sourceSlug !== sourceSlug)) }
    catch (reason) { setError(localizedError(adminLocale, reason, t('Could not delete redirect.'))) }
    finally { setRedirectBusy(false) }
  }

  function addBlock(type: 'hero' | 'richText' | 'image') {
    if (!editing) return

    const firstAsset = mediaAssets[0]
    if (type === 'image' && !firstAsset) {
      setError(t('Upload an image to the media library before adding an Image block.'))
      return
    }

    const block: PageSummary['blocks'][number] = type === 'hero'
      ? { id: createUuid(), type, data: { heading: 'A clear headline', text: 'Explain why this page matters.' } }
      : type === 'richText'
        ? { id: createUuid(), type, data: { content: 'Start writing useful content.' } }
        : {
            id: createUuid(),
            type,
            data: { assetId: firstAsset.id, alt: firstAsset.defaultAlt, caption: '' },
          }

    setEditing({ ...editing, blocks: [...editing.blocks, block] })
    setEditingDirty(true)
  }

  function updateBlock(id: string, field: string, value: string) {
    if (!editing) return

    setEditing({
      ...editing,
      blocks: editing.blocks.map((block) => {
        if (block.id !== id) return block

        if (block.type === 'hero') {
          return {
            ...block,
            data: {
              heading: field === 'heading' ? value : block.data.heading,
              text: field === 'text' ? value : (block.data.text ?? ''),
            },
          }
        }

        if (block.type === 'richText') {
          return { ...block, data: { content: value } }
        }

        if (field === 'assetId') {
          const asset = mediaAssets.find((candidate) => candidate.id === value)
          return {
            ...block,
            data: {
              ...block.data,
              assetId: value,
              alt: asset?.defaultAlt ?? block.data.alt,
            },
          }
        }

        return {
          ...block,
          data: {
            ...block.data,
            [field]: value,
          },
        }
      }),
    })
    setEditingDirty(true)
  }

  function removeBlock(id: string) {
    if (!editing) return
    setEditing({ ...editing, blocks: editing.blocks.filter((block) => block.id !== id) })
    setEditingDirty(true)
    if (selectedBlockId === id) setSelectedBlockId(null)
  }

  function moveBlock(id: string, offset: -1 | 1) {
    if (!editing) return
    const blocks = movePageBlock(editing.blocks, id, offset)
    if (blocks === editing.blocks) return
    setEditing({ ...editing, blocks }); setEditingDirty(true)
  }

  function duplicateBlock(id: string) {
    if (!editing) return
    const blocks = duplicatePageBlock(editing.blocks, id)
    if (blocks === editing.blocks) return
    setEditing({ ...editing, blocks }); setSelectedBlockId(blocks[blocks.findIndex((block) => block.id === id) + 1].id); setEditingDirty(true)
  }

  function dropBlock(targetId: string) {
    if (!editing || !draggedBlockId || draggedBlockId === targetId) return
    const blocks = [...editing.blocks]
    const from = blocks.findIndex((block) => block.id === draggedBlockId)
    const to = blocks.findIndex((block) => block.id === targetId)
    if (from < 0 || to < 0) return
    const [block] = blocks.splice(from, 1)
    blocks.splice(to, 0, block)
    setEditing({ ...editing, blocks }); setEditingDirty(true); setDraggedBlockId(null)
  }

  function insertSection(section: ReusableSection) {
    if (!editing) return
    const copies = copySectionBlocks(section.blocks)
    setEditing({ ...editing, blocks: [...editing.blocks, ...copies] }); setEditingDirty(true)
  }

  async function saveSection() {
    if (!editing || !sectionName.trim() || editing.blocks.length === 0) return
    try {
      const section = await createReusableSection(sectionName.trim(), editing.blocks)
      setSections((current) => [...current, section].sort((left, right) => left.name.localeCompare(right.name)))
      setSectionName(''); setSuccess(t('Reusable section created.'))
    } catch (reason) { setError(localizedError(adminLocale, reason, t('Could not create reusable section.'))) }
  }

  async function removeSection(section: ReusableSection) {
    try { await deleteReusableSection(section.id); setSections((current) => current.filter((item) => item.id !== section.id)) }
    catch (reason) { setError(localizedError(adminLocale, reason, t('Could not delete reusable section.'))) }
  }

  return (
    <Stack spacing={3}>
      <div>
        <Typography component="h1" variant="h1">
          {t('Pages')}
        </Typography>
        <Typography color="text.secondary" sx={{ mt: 0.75 }}>
          {t('Create the first draft. Blocks, preview and publishing will build on this content contract.')}
        </Typography>
      </div>

      {error && <Alert severity="error">{error}</Alert>}
      {success && <Alert severity="success">{success}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Typography variant="h6">{t('New page')}</Typography>
          <Typography color="text.secondary" sx={{ mt: 0.5, mb: 2.5 }}>
            {t('The slug is the future public URL and must be unique. Publish a page with slug 404 to customize the not-found screen.')}
          </Typography>

          <Stack component="form" direction={{ xs: 'column', md: 'row' }} spacing={2} onSubmit={submit}>
            <TextField
              label={t('Title')}
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 200 } }}
            />
            <TextField
              label={t('Slug')}
              value={slug}
              onChange={(event) => setSlug(event.target.value)}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 191, pattern: '[a-z0-9]+(?:-[a-z0-9]+)*' } }}
              helperText={t('Lowercase letters, numbers and hyphens')}
            />
            <TextField label={t('Locale')} value={locale} onChange={(event) => setLocale(event.target.value)} required sx={{ minWidth: 120 }} helperText="en or ru-RU" />
            <TextField select label={t('Translation of')} value={translationSourceId} onChange={(event) => setTranslationSourceId(event.target.value)} sx={{ minWidth: 190 }}>
              <MenuItem value="">{t('New translation group')}</MenuItem>
              {pages.map((page) => <MenuItem key={page.id} value={page.id}>{page.title} ({page.locale})</MenuItem>)}
            </TextField>
            <Button type="submit" variant="contained" disabled={submitting} sx={{ minWidth: 140, alignSelf: 'flex-start' }}>
              {submitting ? t('Creating…') : t('Create draft')}
            </Button>
          </Stack>
        </CardContent>

        <Divider />

        {loading ? (
          <Stack sx={{ py: 6, alignItems: 'center' }}>
            <CircularProgress size={28} />
          </Stack>
        ) : pages.length === 0 ? (
          <Typography color="text.secondary" sx={{ p: 3 }}>
            {t('No pages yet. Create the first draft above.')}
          </Typography>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('Title')}</TableCell>
                  <TableCell>{t('Slug')}</TableCell>
                  <TableCell>{t('Locale')}</TableCell>
                  <TableCell>{t('Status')}</TableCell>
                  <TableCell>{t('Created')}</TableCell>
                  <TableCell align="right">{t('Actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {pages.map((page) => (
                  <TableRow key={page.id} hover>
                    <TableCell sx={{ fontWeight: 700 }}>{page.title}</TableCell>
                    <TableCell>{page.locale === 'en' ? `/${page.slug}` : `/${page.locale}/${page.slug}`}</TableCell>
                    <TableCell><Chip label={page.locale} size="small" variant="outlined" /></TableCell>
                    <TableCell><Chip label={page.status === 'draft' ? t('draft') : page.status} size="small" /></TableCell>
                    <TableCell>{formatDateTime(page.createdAt)}</TableCell>
                    <TableCell align="right">
                      <Button size="small" onClick={() => openEditor(page)}>{t('Edit')}</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </Card>

      {editing && (
        <Card variant="outlined">
          <CardContent component="form" onSubmit={saveRevision}>
            <Stack spacing={3}>
              <Stack direction={{ xs: 'column', md: 'row' }} sx={{ justifyContent: 'space-between', gap: 2 }}>
                <div>
                  <Typography variant="h5">{t('Edit page')}</Typography>
                  <Typography color="text.secondary">{t('Draft revision {{revision}}', { revision: editing.version })}</Typography>
                </div>
                <Stack direction="row" spacing={1}>
                  <Button component="a" href={`/_preview/pages/${editing.id}`} target="_blank" variant="outlined">
                    {t('Preview')}
                  </Button>
                  <Button type="submit" variant="contained" disabled={saving}>
                    {saving ? t('Saving…') : t('Save revision')}
                  </Button>
                  <Button
                    color="success"
                    variant="contained"
                    disabled={publishing || editingDirty}
                    onClick={publishCurrentRevision}
                  >
                    {publishing ? t('Publishing…') : t('Publish')}
                  </Button>
                  <Button color="warning" variant="outlined" disabled={publishing} onClick={() => void unpublishCurrentPage()}>
                    {t('Unpublish')}
                  </Button>
                  <Button color="error" variant="outlined" disabled={publishing || deleting} onClick={() => void deleteCurrentPage()}>
                    {deleting ? t('Deleting…') : t('Delete')}
                  </Button>
                </Stack>
              </Stack>

              {editingDirty && (
                <Alert severity="warning">{t('Save this revision before publishing it.')}</Alert>
              )}

              {publication && (
                <Alert severity="success">
                  {t('Revision {{revision}} is live at', { revision: publication.sourceRevision })}{' '}
                  <a href={`${publicSiteUrl}${publication.url}`} target="_blank" rel="noreferrer">{publication.url}</a>
                  {' '}{t('and available through the')}{' '}
                  <a href={`/api/v1/content/pages${publication.url}`} target="_blank" rel="noreferrer">{t('public API')}</a>.
                </Alert>
              )}

              <PublicationScheduler target="page" targetId={editing.id} sourceVersion={editing.version} dirty={editingDirty} />

              <Card variant="outlined">
                <CardContent>
                  <Typography variant="h6">{t('Revision history')}</Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: .5, mb: 2 }}>{t('Restoring creates a new revision and never changes the published page.')}</Typography>
                  <Stack spacing={1}>
                    {revisions.map((revision) => (
                      <Stack key={revision.revision} direction={{ xs: 'column', sm: 'row' }} sx={{ justifyContent: 'space-between', alignItems: { sm: 'center' }, gap: 1, py: 1, borderTop: 1, borderColor: 'divider' }}>
                        <div><Typography sx={{ fontWeight: 700 }}>{t('Revision {{revision}} · {{title}}', { revision: revision.revision, title: revision.title })}</Typography><Typography variant="body2" color="text.secondary">{formatDateTime(revision.createdAt)} · /{revision.slug}</Typography></div>
                        <Button size="small" variant="outlined" disabled={editingDirty || revision.revision === editing.version || restoringRevision !== null} onClick={() => void restoreRevision(revision.revision)}>{restoringRevision === revision.revision ? t('Restoring…') : t('Restore')}</Button>
                      </Stack>
                    ))}
                  </Stack>
                </CardContent>
              </Card>

              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <TextField
                  label={t('Title')}
                  value={editing.title}
                  onChange={(event) => {
                    setEditing({ ...editing, title: event.target.value })
                    setEditingDirty(true)
                  }}
                  fullWidth
                  required
                />
                <TextField
                  label={t('Slug')}
                  value={editing.slug}
                  onChange={(event) => {
                    setEditing({ ...editing, slug: event.target.value })
                    setEditingDirty(true)
                  }}
                  fullWidth
                  required
                  helperText={t('After publishing, previous slugs become permanent redirects')}
                />
                <TextField label={t('Locale')} value={editing.locale} onChange={(event) => { setEditing({ ...editing, locale: event.target.value }); setEditingDirty(true) }} required helperText={t('Localized variants publish independently')} />
                <TextField select label={t('Translation group')} value={editing.translationGroupId} onChange={(event) => { setEditing({ ...editing, translationGroupId: event.target.value }); setEditingDirty(true) }} sx={{ minWidth: 220 }}>
                  {pages.filter((page, index, all) => all.findIndex((candidate) => candidate.translationGroupId === page.translationGroupId) === index).map((page) => <MenuItem key={page.translationGroupId} value={page.translationGroupId}>{page.title}</MenuItem>)}
                </TextField>
              </Stack>

              <Alert severity="info">
                  <Typography variant="subtitle2" sx={{ mb: 1 }}>{t('Permanent redirects')}</Typography>
                  <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
                    {redirects.map((redirect) => (
                      <Chip
                        key={redirect.sourceSlug}
                        label={`/${redirect.sourceSlug} → /${redirect.targetSlug}`}
                        size="small"
                        variant="outlined"
                        onDelete={() => void removeManualRedirect(redirect.sourceSlug)}
                      />
                    ))}
                  </Stack>
                  <Stack direction="row" spacing={1} sx={{ mt: 2 }}><TextField size="small" label={t('Old slug')} value={redirectSlug} onChange={(event) => setRedirectSlug(event.target.value.toLowerCase())} helperText={t('The page must be published')}/><Button variant="outlined" disabled={!redirectSlug || redirectBusy} onClick={() => void addManualRedirect()}>{t('Add redirect')}</Button></Stack>
                </Alert>

              <Divider />

              <Stack spacing={2}>
                <div>
                  <Typography variant="h6">{t('Theme and layout')}</Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                    {t('Layout settings are versioned with the page and frozen into each published snapshot.')}
                  </Typography>
                </div>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField select label={t('Theme')} value={editing.layout.theme} onChange={(event) => {
                    const theme = themes.find((candidate) => candidate.id === event.target.value)
                    setEditing({ ...editing, layout: { ...editing.layout, theme: event.target.value, template: theme?.templates[0]?.id ?? editing.layout.template } })
                    setEditingDirty(true)
                  }} fullWidth>
                    {themes.map((theme) => <MenuItem key={theme.id} value={theme.id}>{theme.name}</MenuItem>)}
                  </TextField>
                  <TextField select label={t('Template')} value={editing.layout.template} onChange={(event) => {
                    setEditing({ ...editing, layout: { ...editing.layout, template: event.target.value } })
                    setEditingDirty(true)
                  }} fullWidth>
                    {(themes.find((theme) => theme.id === editing.layout.theme)?.templates ?? []).map((template) => <MenuItem key={template.id} value={template.id}>{template.name}</MenuItem>)}
                  </TextField>
                  <TextField select label={t('Header navigation')} value={editing.layout.headerNavigationCode ?? ''} onChange={(event) => {
                    setEditing({ ...editing, layout: { ...editing.layout, headerNavigationCode: event.target.value || null } })
                    setEditingDirty(true)
                  }} fullWidth>
                    <MenuItem value="">{t('No header navigation')}</MenuItem>
                    {navigationMenus.map((menu) => <MenuItem key={menu.id} value={menu.code}>{menu.name}</MenuItem>)}
                  </TextField>
                  <TextField select label={t('Footer navigation')} value={editing.layout.footerNavigationCode ?? ''} onChange={(event) => {
                    setEditing({ ...editing, layout: { ...editing.layout, footerNavigationCode: event.target.value || null } })
                    setEditingDirty(true)
                  }} fullWidth>
                    <MenuItem value="">{t('No footer navigation')}</MenuItem>
                    {navigationMenus.map((menu) => <MenuItem key={menu.id} value={menu.code}>{menu.name}</MenuItem>)}
                  </TextField>
                </Stack>
              </Stack>

              <Divider />

              <Stack spacing={2}>
                <div>
                  <Typography variant="h6">{t('Search and social')}</Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                    {t('These values are versioned with the page and become public only after publishing.')}
                  </Typography>
                </div>

                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField
                    label={t('SEO title')}
                    value={editing.seo.title}
                    onChange={(event) => {
                      setEditing({ ...editing, seo: { ...editing.seo, title: event.target.value } })
                      setEditingDirty(true)
                    }}
                    helperText={t('{{count}}/70 · Empty uses the page title', { count: editing.seo.title.length })}
                    slotProps={{ htmlInput: { maxLength: 70 } }}
                    fullWidth
                  />
                  <TextField
                    select
                    label={t('Social sharing image')}
                    value={editing.seo.openGraphImageAssetId ?? ''}
                    onChange={(event) => {
                      setEditing({
                        ...editing,
                        seo: { ...editing.seo, openGraphImageAssetId: event.target.value || null },
                      })
                      setEditingDirty(true)
                    }}
                    fullWidth
                  >
                    <MenuItem value="">{t('No image')}</MenuItem>
                    {mediaAssets.map((asset) => (
                      <MenuItem key={asset.id} value={asset.id}>{asset.originalName}</MenuItem>
                    ))}
                  </TextField>
                </Stack>

                <TextField
                  label={t('Meta description')}
                  value={editing.seo.description}
                  onChange={(event) => {
                    setEditing({ ...editing, seo: { ...editing.seo, description: event.target.value } })
                    setEditingDirty(true)
                  }}
                  helperText={`${editing.seo.description.length}/170`}
                  slotProps={{ htmlInput: { maxLength: 170 } }}
                  multiline
                  minRows={3}
                />

                <FormControlLabel
                  control={(
                    <Switch
                      checked={editing.seo.noIndex}
                      onChange={(event) => {
                        setEditing({ ...editing, seo: { ...editing.seo, noIndex: event.target.checked } })
                        setEditingDirty(true)
                      }}
                    />
                  )}
                  label={t('Hide this page from search engines and sitemap')}
                />

                <Box sx={{ p: 2.5, border: 1, borderColor: 'divider', borderRadius: 2, maxWidth: 720 }}>
                  <Typography variant="body2" color="success.main" noWrap>
                    {`${publicSiteUrl}${editing.locale === 'en' ? '' : `/${editing.locale}`}/${editing.slug}`}
                  </Typography>
                  <Typography sx={{ color: '#1a0dab', fontSize: 20, mt: 0.5 }}>
                    {editing.seo.title || editing.title}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                    {editing.seo.description || t('Add a concise description of this page for search results.')}
                  </Typography>
                </Box>
              </Stack>

              <Divider />

              <Typography variant="h6">{t('Visual page builder')}</Typography>
              <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
                <Button variant="outlined" onClick={() => addBlock('hero')}>{t('Add Hero')}</Button>
                <Button variant="outlined" onClick={() => addBlock('richText')}>{t('Add RichText')}</Button>
                <Button variant="outlined" onClick={() => addBlock('image')} disabled={mediaAssets.length === 0}>
                  {t('Add Image')}
                </Button>
              </Stack>

              <Card variant="outlined"><CardContent><Stack spacing={2}>
                <div><Typography variant="subtitle1" sx={{ fontWeight: 700 }}>{t('Reusable sections')}</Typography><Typography variant="body2" color="text.secondary">{t('Insertion copies blocks with fresh IDs, so later section changes never rewrite this page.')}</Typography></div>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1}><TextField size="small" label={t('Save current canvas as')} value={sectionName} onChange={(event) => setSectionName(event.target.value)} /><Button variant="outlined" disabled={!sectionName.trim() || editing.blocks.length === 0} onClick={() => void saveSection()}>{t('Save section')}</Button></Stack>
                <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>{sections.map((section) => <Chip key={section.id} label={t('{{name}} · {{count}} blocks', { name: section.name, count: section.blocks.length })} onClick={() => insertSection(section)} onDelete={() => void removeSection(section)} />)}</Stack>
              </Stack></CardContent></Card>

              <Card variant="outlined">
                <CardContent>
                  <Stack spacing={2}>
                    <Stack direction={{ xs: 'column', sm: 'row' }} sx={{ justifyContent: 'space-between', gap: 1 }}>
                      <div>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>{t('Live layout preview')}</Typography>
                        <Typography variant="body2" color="text.secondary">{t('Safe text and media representation; server preview remains authoritative.')}</Typography>
                      </div>
                      <Stack direction="row" spacing={1}>
                        {(['desktop', 'tablet', 'mobile'] as const).map((viewport) => (
                          <Button key={viewport} size="small" variant={builderViewport === viewport ? 'contained' : 'outlined'} onClick={() => setBuilderViewport(viewport)}>
                            {t(viewport)}
                          </Button>
                        ))}
                      </Stack>
                    </Stack>
                    <Box sx={{ overflowX: 'auto', bgcolor: 'grey.100', p: 2, borderRadius: 2 }}>
                      <Box sx={{ width: builderViewport === 'desktop' ? '100%' : builderViewport === 'tablet' ? 720 : 360, maxWidth: '100%', mx: 'auto', bgcolor: editing.layout.theme === 'atelier' ? '#fffaf3' : 'background.paper', border: 1, borderColor: 'divider', borderRadius: 1, overflow: 'hidden', transition: 'width 160ms ease' }}>
                        {editing.blocks.length === 0 ? (
                          <Typography color="text.secondary" sx={{ p: 4, textAlign: 'center' }}>{t('Add blocks to preview the page composition.')}</Typography>
                        ) : editing.blocks.map((block) => {
                          const asset = block.type === 'image' ? mediaAssets.find((candidate) => candidate.id === block.data.assetId) : undefined
                          return (
                            <Box key={block.id} onClick={() => setSelectedBlockId(block.id)} sx={{ p: block.type === 'hero' ? 5 : 3, textAlign: block.type === 'hero' ? 'center' : 'left', border: selectedBlockId === block.id ? 2 : 0, borderColor: 'primary.main', cursor: 'pointer' }}>
                              {block.type === 'hero' ? (
                                <><Typography variant="h4" sx={{ fontFamily: editing.layout.theme === 'atelier' ? 'serif' : 'inherit' }}>{block.data.heading || t('Hero heading')}</Typography><Typography color="text.secondary" sx={{ mt: 1 }}>{block.data.text || t('Supporting text')}</Typography></>
                              ) : block.type === 'richText' ? (
                                <Typography sx={{ whiteSpace: 'pre-wrap' }}>{richTextPreview(block.data.content) || t('Rich text content')}</Typography>
                              ) : asset ? (
                                <Box component="img" src={getMediaThumbnail(asset)} alt={block.data.alt} sx={{ display: 'block', width: '100%', maxHeight: 420, objectFit: 'cover' }} />
                              ) : (
                                <Typography color="text.secondary">{t('Select an image asset.')}</Typography>
                              )}
                            </Box>
                          )
                        })}
                      </Box>
                    </Box>
                  </Stack>
                </CardContent>
              </Card>

              {mediaAssets.length === 0 && (
                <Typography variant="body2" color="text.secondary">
                  {t('Upload media below to enable Image blocks.')}
                </Typography>
              )}

              {editing.blocks.length === 0 && (
                <Alert severity="info">{t('This draft has no blocks. Add Hero or RichText to begin.')}</Alert>
              )}

              {editing.blocks.map((block, index) => (
                <Card key={block.id} variant="outlined" draggable onDragStart={() => setDraggedBlockId(block.id)} onDragOver={(event) => event.preventDefault()} onDrop={() => dropBlock(block.id)} onClick={() => setSelectedBlockId(block.id)} sx={{ borderColor: selectedBlockId === block.id ? 'primary.main' : 'divider', borderWidth: selectedBlockId === block.id ? 2 : 1, cursor: 'grab' }}>
                  <CardContent>
                    <Stack spacing={2}>
                      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                          {index + 1}. {block.type === 'hero' ? t('Hero') : block.type === 'richText' ? 'RichText' : t('Image')}
                        </Typography>
                        <Stack direction="row" spacing={.5}>
                          <Button size="small" disabled={index === 0} onClick={() => moveBlock(block.id, -1)}>{t('Up')}</Button>
                          <Button size="small" disabled={index === editing.blocks.length - 1} onClick={() => moveBlock(block.id, 1)}>{t('Down')}</Button>
                          <Button size="small" onClick={() => duplicateBlock(block.id)}>{t('Duplicate')}</Button>
                          <Button color="error" size="small" onClick={() => removeBlock(block.id)}>{t('Remove')}</Button>
                        </Stack>
                      </Stack>

                      {block.type === 'hero' ? (
                        <>
                          <TextField
                            label={t('Heading')}
                            value={block.data.heading}
                            onChange={(event) => updateBlock(block.id, 'heading', event.target.value)}
                            required
                          />
                          <TextField
                            label={t('Supporting text')}
                            value={block.data.text ?? ''}
                            onChange={(event) => updateBlock(block.id, 'text', event.target.value)}
                            multiline
                            minRows={2}
                          />
                        </>
                      ) : block.type === 'richText' ? (
                        <RichTextEditor
                          value={block.data.content}
                          onChange={(content) => updateBlock(block.id, 'content', content)}
                        />
                      ) : (
                        <>
                          {(() => {
                            const asset = mediaAssets.find((candidate) => candidate.id === block.data.assetId)
                            const thumbnail = asset ? getMediaThumbnail(asset) : undefined

                            return thumbnail ? (
                              <Box
                                component="img"
                                src={thumbnail}
                                alt=""
                                sx={{ width: '100%', maxHeight: 320, objectFit: 'contain', bgcolor: 'grey.100', borderRadius: 1 }}
                              />
                            ) : null
                          })()}
                          <TextField
                            select
                            label={t('Media asset')}
                            value={block.data.assetId}
                            onChange={(event) => updateBlock(block.id, 'assetId', event.target.value)}
                            required
                          >
                            {mediaAssets.map((asset) => (
                              <MenuItem key={asset.id} value={asset.id}>
                                {asset.originalName} ({asset.width} × {asset.height})
                              </MenuItem>
                            ))}
                          </TextField>
                          <TextField
                            label={t('Alternative text')}
                            value={block.data.alt}
                            onChange={(event) => updateBlock(block.id, 'alt', event.target.value)}
                            required
                            slotProps={{ htmlInput: { maxLength: 300 } }}
                            helperText={t('Describe what the image communicates for accessibility and SEO')}
                          />
                          <TextField
                            label={t('Caption')}
                            value={block.data.caption ?? ''}
                            onChange={(event) => updateBlock(block.id, 'caption', event.target.value)}
                            multiline
                            minRows={2}
                            slotProps={{ htmlInput: { maxLength: 500 } }}
                          />
                        </>
                      )}
                    </Stack>
                  </CardContent>
                </Card>
              ))}
            </Stack>
          </CardContent>
        </Card>
      )}
    </Stack>
  )
}

function localizedError(locale: string, reason: unknown, fallback: string): string {
  return locale === 'en' && reason instanceof Error ? reason.message : fallback
}
