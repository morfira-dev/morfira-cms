import { type ChangeEvent, type FormEvent, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  CircularProgress,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from '../../ui'
import { deleteMedia, type MediaAsset, updateMediaMetadata, uploadMedia } from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'

interface MediaLibraryProps {
  assets: MediaAsset[]
  loading: boolean
  onChange: (assets: MediaAsset[]) => void
}

export function getMediaThumbnail(asset: MediaAsset): string | undefined {
  return [...asset.variants].sort((left, right) => left.width - right.width)[0]?.url
}

export function MediaLibrary({ assets, loading, onChange }: MediaLibraryProps) {
  const { errorMessage, t } = useAdminLocale()
  const [file, setFile] = useState<File | null>(null)
  const [alt, setAlt] = useState('')
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [inputKey, setInputKey] = useState(0)
  const [query, setQuery] = useState('')
  const [collectionFilter, setCollectionFilter] = useState('')
  const [editingId, setEditingId] = useState<string | null>(null)
  const [metadataAlt, setMetadataAlt] = useState('')
  const [collection, setCollection] = useState('')
  const [tags, setTags] = useState('')
  const [saving, setSaving] = useState(false)

  function chooseFile(event: ChangeEvent<HTMLInputElement>) {
    const selected = event.target.files?.[0] ?? null
    setFile(selected)
    setError(null)

    if (selected && !alt) {
      setAlt(selected.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' '))
    }
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!file) return

    if (file.size > 10 * 1024 * 1024) {
      setError(t('Images must be 10 MB or smaller.'))
      return
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setError(t('Choose a JPEG, PNG or WebP image.'))
      return
    }

    setUploading(true)
    setError(null)

    try {
      const asset = await uploadMedia(file, alt.trim())
      onChange([asset, ...assets])
      setFile(null)
      setAlt('')
      setInputKey((current) => current + 1)
    } catch (reason) {
      setError(errorMessage(reason, 'Could not upload the image.'))
    } finally {
      setUploading(false)
    }
  }

  function startEditing(asset: MediaAsset) {
    setEditingId(asset.id)
    setMetadataAlt(asset.defaultAlt)
    setCollection(asset.collection ?? '')
    setTags(asset.tags.join(', '))
    setError(null)
  }

  async function saveMetadata(asset: MediaAsset) {
    setSaving(true)
    setError(null)
    try {
      const updated = await updateMediaMetadata(asset.id, {
        defaultAlt: metadataAlt.trim(),
        collection: collection.trim() || null,
        tags: tags.split(',').map((tag) => tag.trim().toLowerCase()).filter(Boolean),
      })
      onChange(assets.map((item) => item.id === updated.id ? updated : item))
      setEditingId(null)
    } catch (reason) {
      setError(errorMessage(reason, 'Could not update media metadata.'))
    } finally {
      setSaving(false)
    }
  }

  async function removeAsset(asset: MediaAsset) {
    if (!window.confirm(t('Delete {{name}}? This is only allowed when it is unused.', { name: asset.originalName }))) return
    setSaving(true)
    setError(null)
    try {
      await deleteMedia(asset.id)
      onChange(assets.filter((item) => item.id !== asset.id))
    } catch (reason) {
      setError(errorMessage(reason, 'Could not delete the media asset.'))
    } finally {
      setSaving(false)
    }
  }

  const normalizedQuery = query.trim().toLowerCase()
  const collections = [...new Set(assets.map((asset) => asset.collection).filter((value): value is string => value !== null))].sort()
  const visibleAssets = assets.filter((asset) =>
    (collectionFilter === '' || asset.collection === collectionFilter)
    && (normalizedQuery === '' || [asset.originalName, asset.defaultAlt, asset.collection ?? '', ...asset.tags]
      .some((value) => value.toLowerCase().includes(normalizedQuery))),
  )

  return (
    <Stack spacing={3}>
      <div>
        <Typography component="h1" variant="h1">{t('Media')}</Typography>
        <Typography color="text.secondary" sx={{ mt: 0.75 }}>
          {t('Upload reusable images once. The CMS creates safe, responsive variants for pages and APIs.')}
        </Typography>
      </div>

      {error && <Alert severity="error">{error}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Stack component="form" spacing={2} onSubmit={submit}>
            <div>
              <Typography variant="h6">{t('Upload image')}</Typography>
              <Typography color="text.secondary" sx={{ mt: 0.5 }}>
                {t('JPEG, PNG or WebP, up to 10 MB. Alternative text is required for accessibility and SEO.')}
              </Typography>
            </div>

            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ alignItems: { md: 'flex-start' } }}>
              <Button component="label" variant="outlined" sx={{ minWidth: 160 }}>
                {file ? t('Change image') : t('Choose image')}
                <input
                  key={inputKey}
                  hidden
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={chooseFile}
                />
              </Button>
              <TextField
                label={t('Alternative text')}
                value={alt}
                onChange={(event) => setAlt(event.target.value)}
                required
                fullWidth
                slotProps={{ htmlInput: { maxLength: 300 } }}
                helperText={file?.name ?? t('Describe the image, not its filename')}
              />
              <Button
                type="submit"
                variant="contained"
                disabled={!file || !alt.trim() || uploading}
                sx={{ minWidth: 140 }}
              >
                {uploading ? t('Uploading…') : t('Upload')}
              </Button>
            </Stack>
          </Stack>
        </CardContent>
      </Card>

      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
        <TextField
          label={t('Search media')}
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t('Filename, alt text, collection or tag')}
          fullWidth
        />
        <TextField select label={t('Collection')} value={collectionFilter} onChange={(event) => setCollectionFilter(event.target.value)} sx={{ minWidth: 220 }}>
          <MenuItem value="">{t('All collections')}</MenuItem>
          {collections.map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}
        </TextField>
      </Stack>

      {loading ? (
        <Stack sx={{ py: 5, alignItems: 'center' }}><CircularProgress size={28} /></Stack>
      ) : assets.length === 0 ? (
        <Alert severity="info">{t('No images yet. Upload the first asset above.')}</Alert>
      ) : visibleAssets.length === 0 ? (
        <Alert severity="info">{t('No media matches this search.')}</Alert>
      ) : (
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(210px, 1fr))', gap: 2 }}>
          {visibleAssets.map((asset) => {
            const thumbnail = getMediaThumbnail(asset)
            return (
              <Card key={asset.id} variant="outlined">
                {thumbnail && (
                  <Box
                    component="img"
                    src={thumbnail}
                    alt={asset.defaultAlt}
                    loading="lazy"
                    sx={{ width: '100%', aspectRatio: '4 / 3', objectFit: 'cover', display: 'block', bgcolor: 'grey.100' }}
                  />
                )}
                <CardContent>
                  <Typography noWrap title={asset.originalName} sx={{ fontWeight: 700 }}>{asset.originalName}</Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                    {asset.width} × {asset.height} · {formatBytes(asset.size)}
                  </Typography>
                  {editingId === asset.id ? (
                    <Stack spacing={1.5} sx={{ mt: 1.5 }}>
                      <TextField label={t('Alternative text')} value={metadataAlt} onChange={(event) => setMetadataAlt(event.target.value)} required />
                      <TextField label={t('Collection')} value={collection} onChange={(event) => setCollection(event.target.value)} />
                      <TextField label={t('Tags')} value={tags} onChange={(event) => setTags(event.target.value)} helperText={t('Comma-separated lowercase slugs')} />
                      <Stack direction="row" spacing={1}>
                        <Button size="small" variant="contained" disabled={saving || !metadataAlt.trim()} onClick={() => void saveMetadata(asset)}>{t('Save')}</Button>
                        <Button size="small" disabled={saving} onClick={() => setEditingId(null)}>{t('Cancel')}</Button>
                      </Stack>
                    </Stack>
                  ) : (
                    <>
                      <Typography variant="body2" sx={{ mt: 1 }}>{asset.defaultAlt}</Typography>
                      {asset.collection && <Typography variant="caption" color="text.secondary">{t('Collection: {{collection}}', { collection: asset.collection })}</Typography>}
                      {asset.tags.length > 0 && <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>{t('Tags: {{tags}}', { tags: asset.tags.join(', ') })}</Typography>}
                      <Stack direction="row" spacing={1} sx={{ mt: 1.5 }}>
                        <Button size="small" onClick={() => startEditing(asset)}>{t('Edit')}</Button>
                        <Button size="small" color="error" disabled={saving} onClick={() => void removeAsset(asset)}>{t('Delete')}</Button>
                      </Stack>
                    </>
                  )}
                </CardContent>
              </Card>
            )
          })}
        </Box>
      )}
    </Stack>
  )
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
