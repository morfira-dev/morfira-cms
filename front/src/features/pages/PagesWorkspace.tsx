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
import { getMediaThumbnail } from '../media/MediaLibrary'
import {
  createPage,
  getPageRedirects,
  getPages,
  type PageSummary,
  type PublishedPage,
  type PublishedRedirect,
  publishPage,
  updatePage,
} from './api'

const publicSiteUrl = import.meta.env.VITE_PUBLIC_SITE_URL || window.location.origin

interface PagesWorkspaceProps {
  mediaAssets: MediaAsset[]
}

export function PagesWorkspace({ mediaAssets }: PagesWorkspaceProps) {
  const [pages, setPages] = useState<PageSummary[]>([])
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [slug, setSlug] = useState('')
  const [editing, setEditing] = useState<PageSummary | null>(null)
  const [editingDirty, setEditingDirty] = useState(false)
  const [publication, setPublication] = useState<PublishedPage | null>(null)
  const [redirects, setRedirects] = useState<PublishedRedirect[]>([])

  useEffect(() => {
    const controller = new AbortController()

    getPages(controller.signal)
      .then(setPages)
      .catch((reason: unknown) => {
        if (reason instanceof DOMException && reason.name === 'AbortError') {
          return
        }

        setError(reason instanceof Error ? reason.message : 'Could not load pages.')
      })
      .finally(() => setLoading(false))

    return () => controller.abort()
  }, [])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      const page = await createPage(title, slug)
      setPages((current) => [page, ...current])
      setTitle('')
      setSlug('')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not create the page.')
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
        version: editing.version,
      })
      setEditing(saved)
      setEditingDirty(false)
      setPages((current) => current.map((page) => page.id === saved.id ? saved : page))
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not save the revision.')
    } finally {
      setSaving(false)
    }
  }

  async function publishCurrentRevision() {
    if (!editing || editingDirty) return

    setPublishing(true)
    setError(null)

    try {
      const published = await publishPage(editing.id, editing.version)
      setPublication(published)
      try {
        setRedirects(await getPageRedirects(editing.id))
      } catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Published, but redirect history could not be refreshed.')
      }
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not publish the page.')
    } finally {
      setPublishing(false)
    }
  }

  function openEditor(page: PageSummary) {
    setEditing(structuredClone(page))
    setEditingDirty(false)
    setPublication(null)
    setRedirects([])
    getPageRedirects(page.id)
      .then(setRedirects)
      .catch((reason: unknown) => {
        setError(reason instanceof Error ? reason.message : 'Could not load redirect history.')
      })
  }

  function addBlock(type: 'hero' | 'richText' | 'image') {
    if (!editing) return

    const firstAsset = mediaAssets[0]
    if (type === 'image' && !firstAsset) {
      setError('Upload an image to the media library before adding an Image block.')
      return
    }

    const block: PageSummary['blocks'][number] = type === 'hero'
      ? { id: crypto.randomUUID(), type, data: { heading: 'A clear headline', text: 'Explain why this page matters.' } }
      : type === 'richText'
        ? { id: crypto.randomUUID(), type, data: { content: 'Start writing useful content.' } }
        : {
            id: crypto.randomUUID(),
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
  }

  return (
    <Stack spacing={3}>
      <div>
        <Typography component="h1" variant="h1">
          Pages
        </Typography>
        <Typography color="text.secondary" sx={{ mt: 0.75 }}>
          Create the first draft. Blocks, preview and publishing will build on this content contract.
        </Typography>
      </div>

      {error && <Alert severity="error">{error}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Typography variant="h6">New page</Typography>
          <Typography color="text.secondary" sx={{ mt: 0.5, mb: 2.5 }}>
            The slug is the future public URL and must be unique. Publish a page with slug 404 to customize the not-found screen.
          </Typography>

          <Stack component="form" direction={{ xs: 'column', md: 'row' }} spacing={2} onSubmit={submit}>
            <TextField
              label="Title"
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 200 } }}
            />
            <TextField
              label="Slug"
              value={slug}
              onChange={(event) => setSlug(event.target.value)}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 191, pattern: '[a-z0-9]+(?:-[a-z0-9]+)*' } }}
              helperText="Lowercase letters, numbers and hyphens"
            />
            <Button type="submit" variant="contained" disabled={submitting} sx={{ minWidth: 140, alignSelf: 'flex-start' }}>
              {submitting ? 'Creating…' : 'Create draft'}
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
            No pages yet. Create the first draft above.
          </Typography>
        ) : (
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Title</TableCell>
                  <TableCell>Slug</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>Created</TableCell>
                  <TableCell align="right">Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {pages.map((page) => (
                  <TableRow key={page.id} hover>
                    <TableCell sx={{ fontWeight: 700 }}>{page.title}</TableCell>
                    <TableCell>/{page.slug}</TableCell>
                    <TableCell><Chip label={page.status} size="small" /></TableCell>
                    <TableCell>{new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(page.createdAt))}</TableCell>
                    <TableCell align="right">
                      <Button size="small" onClick={() => openEditor(page)}>Edit</Button>
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
                  <Typography variant="h5">Edit page</Typography>
                  <Typography color="text.secondary">Draft revision {editing.version}</Typography>
                </div>
                <Stack direction="row" spacing={1}>
                  <Button component="a" href={`/_preview/pages/${editing.id}`} target="_blank" variant="outlined">
                    Preview
                  </Button>
                  <Button type="submit" variant="contained" disabled={saving}>
                    {saving ? 'Saving…' : 'Save revision'}
                  </Button>
                  <Button
                    color="success"
                    variant="contained"
                    disabled={publishing || editingDirty}
                    onClick={publishCurrentRevision}
                  >
                    {publishing ? 'Publishing…' : 'Publish'}
                  </Button>
                </Stack>
              </Stack>

              {editingDirty && (
                <Alert severity="warning">Save this revision before publishing it.</Alert>
              )}

              {publication && (
                <Alert severity="success">
                  Revision {publication.sourceRevision} is live at{' '}
                  <a href={`${publicSiteUrl}/${publication.slug}`} target="_blank" rel="noreferrer">/{publication.slug}</a>
                  {' '}and available through the{' '}
                  <a href={`/api/v1/content/pages/${publication.slug}`} target="_blank" rel="noreferrer">public API</a>.
                </Alert>
              )}

              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <TextField
                  label="Title"
                  value={editing.title}
                  onChange={(event) => {
                    setEditing({ ...editing, title: event.target.value })
                    setEditingDirty(true)
                  }}
                  fullWidth
                  required
                />
                <TextField
                  label="Slug"
                  value={editing.slug}
                  onChange={(event) => {
                    setEditing({ ...editing, slug: event.target.value })
                    setEditingDirty(true)
                  }}
                  fullWidth
                  required
                  helperText="After publishing, previous slugs become permanent redirects"
                />
              </Stack>

              {redirects.length > 0 && (
                <Alert severity="info">
                  <Typography variant="subtitle2" sx={{ mb: 1 }}>Permanent redirect history</Typography>
                  <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
                    {redirects.map((redirect) => (
                      <Chip
                        key={redirect.sourceSlug}
                        label={`/${redirect.sourceSlug} → /${redirect.targetSlug}`}
                        size="small"
                        variant="outlined"
                      />
                    ))}
                  </Stack>
                </Alert>
              )}

              <Divider />

              <Stack spacing={2}>
                <div>
                  <Typography variant="h6">Search and social</Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                    These values are versioned with the page and become public only after publishing.
                  </Typography>
                </div>

                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField
                    label="SEO title"
                    value={editing.seo.title}
                    onChange={(event) => {
                      setEditing({ ...editing, seo: { ...editing.seo, title: event.target.value } })
                      setEditingDirty(true)
                    }}
                    helperText={`${editing.seo.title.length}/70 · Empty uses the page title`}
                    slotProps={{ htmlInput: { maxLength: 70 } }}
                    fullWidth
                  />
                  <TextField
                    select
                    label="Social sharing image"
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
                    <MenuItem value="">No image</MenuItem>
                    {mediaAssets.map((asset) => (
                      <MenuItem key={asset.id} value={asset.id}>{asset.originalName}</MenuItem>
                    ))}
                  </TextField>
                </Stack>

                <TextField
                  label="Meta description"
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
                  label="Hide this page from search engines and sitemap"
                />

                <Box sx={{ p: 2.5, border: 1, borderColor: 'divider', borderRadius: 2, maxWidth: 720 }}>
                  <Typography variant="body2" color="success.main" noWrap>
                    {`${publicSiteUrl}/${editing.slug}`}
                  </Typography>
                  <Typography sx={{ color: '#1a0dab', fontSize: 20, mt: 0.5 }}>
                    {editing.seo.title || editing.title}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                    {editing.seo.description || 'Add a concise description of this page for search results.'}
                  </Typography>
                </Box>
              </Stack>

              <Divider />

              <Stack direction="row" spacing={1}>
                <Button variant="outlined" onClick={() => addBlock('hero')}>Add Hero</Button>
                <Button variant="outlined" onClick={() => addBlock('richText')}>Add RichText</Button>
                <Button variant="outlined" onClick={() => addBlock('image')} disabled={mediaAssets.length === 0}>
                  Add Image
                </Button>
              </Stack>

              {mediaAssets.length === 0 && (
                <Typography variant="body2" color="text.secondary">
                  Upload media below to enable Image blocks.
                </Typography>
              )}

              {editing.blocks.length === 0 && (
                <Alert severity="info">This draft has no blocks. Add Hero or RichText to begin.</Alert>
              )}

              {editing.blocks.map((block, index) => (
                <Card key={block.id} variant="outlined">
                  <CardContent>
                    <Stack spacing={2}>
                      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                          {index + 1}. {block.type === 'hero' ? 'Hero' : block.type === 'richText' ? 'RichText' : 'Image'}
                        </Typography>
                        <Button color="error" size="small" onClick={() => removeBlock(block.id)}>Remove</Button>
                      </Stack>

                      {block.type === 'hero' ? (
                        <>
                          <TextField
                            label="Heading"
                            value={block.data.heading}
                            onChange={(event) => updateBlock(block.id, 'heading', event.target.value)}
                            required
                          />
                          <TextField
                            label="Supporting text"
                            value={block.data.text ?? ''}
                            onChange={(event) => updateBlock(block.id, 'text', event.target.value)}
                            multiline
                            minRows={2}
                          />
                        </>
                      ) : block.type === 'richText' ? (
                        <TextField
                          label="Content"
                          value={block.data.content}
                          onChange={(event) => updateBlock(block.id, 'content', event.target.value)}
                          multiline
                          minRows={6}
                          required
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
                            label="Media asset"
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
                            label="Alternative text"
                            value={block.data.alt}
                            onChange={(event) => updateBlock(block.id, 'alt', event.target.value)}
                            required
                            slotProps={{ htmlInput: { maxLength: 300 } }}
                            helperText="Describe what the image communicates for accessibility and SEO"
                          />
                          <TextField
                            label="Caption"
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
