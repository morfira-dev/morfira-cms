import { type ChangeEvent, type FormEvent, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  CircularProgress,
  Stack,
  TextField,
  Typography,
} from '../../ui'
import { type MediaAsset, uploadMedia } from './api'

interface MediaLibraryProps {
  assets: MediaAsset[]
  loading: boolean
  onUploaded: (asset: MediaAsset) => void
}

export function getMediaThumbnail(asset: MediaAsset): string | undefined {
  return [...asset.variants].sort((left, right) => left.width - right.width)[0]?.url
}

export function MediaLibrary({ assets, loading, onUploaded }: MediaLibraryProps) {
  const [file, setFile] = useState<File | null>(null)
  const [alt, setAlt] = useState('')
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [inputKey, setInputKey] = useState(0)

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
      setError('Images must be 10 MB or smaller.')
      return
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setError('Choose a JPEG, PNG or WebP image.')
      return
    }

    setUploading(true)
    setError(null)

    try {
      const asset = await uploadMedia(file, alt.trim())
      onUploaded(asset)
      setFile(null)
      setAlt('')
      setInputKey((current) => current + 1)
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Could not upload the image.')
    } finally {
      setUploading(false)
    }
  }

  return (
    <Stack spacing={3}>
      <div>
        <Typography component="h1" variant="h1">Media</Typography>
        <Typography color="text.secondary" sx={{ mt: 0.75 }}>
          Upload reusable images once. The CMS creates safe, responsive variants for pages and APIs.
        </Typography>
      </div>

      {error && <Alert severity="error">{error}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Stack component="form" spacing={2} onSubmit={submit}>
            <div>
              <Typography variant="h6">Upload image</Typography>
              <Typography color="text.secondary" sx={{ mt: 0.5 }}>
                JPEG, PNG or WebP, up to 10 MB. Alternative text is required for accessibility and SEO.
              </Typography>
            </div>

            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ alignItems: { md: 'flex-start' } }}>
              <Button component="label" variant="outlined" sx={{ minWidth: 160 }}>
                {file ? 'Change image' : 'Choose image'}
                <input
                  key={inputKey}
                  hidden
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={chooseFile}
                />
              </Button>
              <TextField
                label="Alternative text"
                value={alt}
                onChange={(event) => setAlt(event.target.value)}
                required
                fullWidth
                slotProps={{ htmlInput: { maxLength: 300 } }}
                helperText={file?.name ?? 'Describe the image, not its filename'}
              />
              <Button
                type="submit"
                variant="contained"
                disabled={!file || !alt.trim() || uploading}
                sx={{ minWidth: 140 }}
              >
                {uploading ? 'Uploading…' : 'Upload'}
              </Button>
            </Stack>
          </Stack>
        </CardContent>
      </Card>

      {loading ? (
        <Stack sx={{ py: 5, alignItems: 'center' }}><CircularProgress size={28} /></Stack>
      ) : assets.length === 0 ? (
        <Alert severity="info">No images yet. Upload the first asset above.</Alert>
      ) : (
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(210px, 1fr))', gap: 2 }}>
          {assets.map((asset) => {
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
                  <Typography variant="body2" sx={{ mt: 1 }}>{asset.defaultAlt}</Typography>
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
