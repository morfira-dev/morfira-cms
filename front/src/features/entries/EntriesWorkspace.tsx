import { type FormEvent, useEffect, useRef, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  FormControlLabel,
  MenuItem,
  Stack,
  Switch,
  TextField,
  Typography,
} from '../../ui'
import type { ContentField, ContentType } from '../contentTypes/api'
import type { MediaAsset } from '../media/api'
import { PublicationScheduler } from '../publishing/PublicationScheduler'
import { EntryTaxonomyAssignments } from '../taxonomy/EntryTaxonomyAssignments'
import type { Taxonomy } from '../taxonomy/api'
import {
  createEntry,
  type Entry,
  type EntryValue,
  type EntryValues,
  getEntries,
  getEntry,
  publishEntry,
  updateEntry,
} from './api'
import { initializeEntryValues, publishedEntryUrl, setEntryValue, validateEntryValues } from './values'

interface EntriesWorkspaceProps { contentTypes: ContentType[]; mediaAssets: MediaAsset[]; taxonomies: Taxonomy[] }
interface EntryForm { id: string | null; title: string; slug: string; values: EntryValues; version: number | null }

export function EntriesWorkspace({ contentTypes, mediaAssets, taxonomies }: EntriesWorkspaceProps) {
  const [contentTypeId, setContentTypeId] = useState('')
  const [entries, setEntries] = useState<Entry[]>([])
  const [editing, setEditing] = useState<EntryForm | null>(null)
  const [dirty, setDirty] = useState(false)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [publishedUrl, setPublishedUrl] = useState<string | null>(null)
  const request = useRef<AbortController | null>(null)

  const contentType = contentTypes.find((type) => type.id === contentTypeId) ?? null

  useEffect(() => {
    if (!contentTypeId) {
      setEntries([])
      setEditing(null)
      return
    }
    request.current?.abort()
    const controller = new AbortController()
    request.current = controller
    setLoading(true)
    setError(null)
    getEntries(contentTypeId, controller.signal)
      .then(setEntries)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(errorMessage(reason, 'Could not load entries.'))
      })
      .finally(() => { if (request.current === controller) setLoading(false) })
    setEditing(null)
    setDirty(false)
    setSuccess(null)
    setPublishedUrl(null)
    return () => controller.abort()
  }, [contentTypeId])

  async function selectEntry(id: string) {
    if (!contentType) return
    request.current?.abort()
    const controller = new AbortController()
    request.current = controller
    setLoading(true)
    setError(null)
    try {
      const entry = await getEntry(id, controller.signal)
      setEditing(toForm(entry, contentType))
      setDirty(false)
      setSuccess(null)
      setPublishedUrl(null)
    } catch (reason) {
      if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(errorMessage(reason, 'Could not load entry.'))
    } finally {
      if (request.current === controller) setLoading(false)
    }
  }

  function newEntry() {
    if (!contentType) return
    setEditing({ id: null, title: '', slug: '', values: initializeEntryValues(contentType.fields), version: null })
    setDirty(true)
    setError(null)
    setSuccess(null)
    setPublishedUrl(null)
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!editing || !contentType) return
    const validation = validateForm(editing, contentType)
    if (validation) { setError(validation); return }
    setSaving(true)
    setError(null)
    try {
      const saved = editing.id === null
        ? await createEntry({ contentTypeId: contentType.id, title: editing.title.trim(), slug: editing.slug, values: editing.values })
        : await updateEntry(editing.id, { title: editing.title.trim(), slug: editing.slug, values: editing.values, version: editing.version! })
      setEditing(toForm(saved, contentType))
      setEntries((current) => editing.id === null ? [saved, ...current] : current.map((entry) => entry.id === saved.id ? saved : entry))
      setDirty(false)
      setPublishedUrl(null)
      setSuccess(`Draft version ${saved.version} was saved.`)
    } catch (reason) {
      setError(`${errorMessage(reason, 'Could not save entry.')} Reload it if another editor changed it.`)
    } finally { setSaving(false) }
  }

  async function publish() {
    if (!editing?.id || editing.version === null || dirty || !contentType) return
    setPublishing(true); setError(null); setSuccess(null)
    try {
      const published = await publishEntry(editing.id, editing.version)
      const url = publishedEntryUrl(published)
      setPublishedUrl(url)
      setSuccess(`Version ${published.sourceVersion} is published.`)
    } catch (reason) { setError(errorMessage(reason, 'Could not publish entry.')) }
    finally { setPublishing(false) }
  }

  function changeValue(key: string, value: EntryValue) {
    if (!editing) return
    setEditing({ ...editing, values: setEntryValue(editing.values, key, value) })
    setDirty(true); setSuccess(null); setPublishedUrl(null)
  }

  return (
    <Stack spacing={3}>
      <div><Typography component="h1" variant="h1">Entries</Typography><Typography color="text.secondary" sx={{ mt: 0.75 }}>Create structured content with a form generated from its schema.</Typography></div>
      {error && <Alert severity="error">{error}</Alert>}
      {success && <Alert severity="success">{success}</Alert>}

      <TextField select label="Content type" value={contentTypeId} onChange={(event) => setContentTypeId(event.target.value)} helperText="Choose the schema for these entries">
        {contentTypes.map((type) => <MenuItem key={type.id} value={type.id}>{type.name} ({type.apiName})</MenuItem>)}
      </TextField>

      {!contentType ? <Alert severity="info">Create and select a content type first.</Alert> : (
        <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: '280px minmax(0, 1fr)' }, gap: 3 }}>
          <Card variant="outlined" sx={{ alignSelf: 'start' }}><CardContent>
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}><Typography variant="h6">{contentType.name}</Typography><Button size="small" variant="contained" onClick={newEntry}>New</Button></Stack>
            {loading && !editing ? <Stack sx={{ py: 4, alignItems: 'center' }}><CircularProgress size={26} /></Stack> : (
              <Stack spacing={1} sx={{ mt: 2 }}>
                {entries.map((entry) => <Button key={entry.id} variant={editing?.id === entry.id ? 'contained' : 'outlined'} onClick={() => void selectEntry(entry.id)} sx={{ justifyContent: 'space-between' }}><span>{entry.title}</span><Chip label={`v${entry.version}`} size="small" /></Button>)}
                {entries.length === 0 && <Typography color="text.secondary">No entries yet.</Typography>}
              </Stack>
            )}
          </CardContent></Card>

          {loading && editing ? <Stack sx={{ minHeight: 240, alignItems: 'center', justifyContent: 'center' }}><CircularProgress /></Stack> : !editing ? <Alert severity="info">Create or select an entry.</Alert> : (
            <Card variant="outlined"><CardContent component="form" onSubmit={save}>
              <Stack spacing={3}>
                <Stack direction={{ xs: 'column', md: 'row' }} sx={{ justifyContent: 'space-between', gap: 2 }}>
                  <div><Typography variant="h5">{editing.id ? 'Edit entry' : 'New entry'}</Typography><Typography color="text.secondary">{editing.version ? `Draft version ${editing.version}` : 'Not saved yet'}</Typography></div>
                  <Stack direction="row" spacing={1}>
                    {publishedUrl && <Button component="a" href={publishedUrl} target="_blank" variant="outlined">Open published API</Button>}
                    <Button type="submit" variant="contained" disabled={!dirty || saving}>{saving ? 'Saving…' : 'Save'}</Button>
                    <Button color="success" variant="contained" disabled={!editing.id || dirty || publishing} onClick={() => void publish()}>{publishing ? 'Publishing…' : 'Publish'}</Button>
                  </Stack>
                </Stack>
                {dirty && editing.id && <Alert severity="warning">Save this version before publishing it.</Alert>}
                {editing.id && editing.version !== null && <PublicationScheduler target="entry" targetId={editing.id} sourceVersion={editing.version} dirty={dirty} />}
                {editing.id && <EntryTaxonomyAssignments entryId={editing.id} taxonomies={taxonomies} />}
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField label="Title" value={editing.title} onChange={(event) => { setEditing({ ...editing, title: event.target.value }); setDirty(true); setPublishedUrl(null) }} fullWidth required />
                  <TextField label="Slug" value={editing.slug} onChange={(event) => { setEditing({ ...editing, slug: event.target.value.toLowerCase() }); setDirty(true); setPublishedUrl(null) }} fullWidth required helperText="Lowercase letters, numbers and hyphens" />
                </Stack>
                {contentType.fields.length === 0 && <Alert severity="info">This schema has no custom fields.</Alert>}
                {contentType.fields.map((field) => <EntryField key={field.id} field={field} value={editing.values[field.key] ?? null} mediaAssets={mediaAssets} contentTypes={contentTypes} onChange={(value) => changeValue(field.key, value)} />)}
              </Stack>
            </CardContent></Card>
          )}
        </Box>
      )}
    </Stack>
  )
}

function EntryField({ field, value, mediaAssets, contentTypes, onChange }: { field: ContentField; value: EntryValue; mediaAssets: MediaAsset[]; contentTypes: ContentType[]; onChange: (value: EntryValue) => void }) {
  if (field.type === 'boolean') return <FormControlLabel control={<Switch checked={value === true} onChange={(event) => onChange(event.target.checked)} />} label={`${field.label}${field.required ? ' *' : ''}`} />
  if (field.type === 'media') return <TextField select label={field.label} value={typeof value === 'string' ? value : ''} onChange={(event) => onChange(event.target.value || null)} required={field.required} helperText="Choose an image from the media library"><MenuItem value="">None</MenuItem>{mediaAssets.map((asset) => <MenuItem key={asset.id} value={asset.id}>{asset.originalName}</MenuItem>)}</TextField>
  if (field.type === 'number') return <TextField type="number" label={field.label} value={typeof value === 'number' ? value : ''} onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))} required={field.required} />
  if(field.type==='relation') return <RelationField field={field} value={value} contentTypes={contentTypes} onChange={onChange}/>
  return <TextField type={field.type === 'date' ? 'date' : 'text'} label={field.label} value={typeof value === 'string' ? value : ''} onChange={(event) => onChange(event.target.value || null)} required={field.required} multiline={field.type === 'textarea'} minRows={field.type === 'textarea' ? 5 : undefined} slotProps={field.type === 'date' ? { inputLabel: { shrink: true } } : undefined} />
}

function RelationField({field,value,contentTypes,onChange}:{field:ContentField;value:EntryValue;contentTypes:ContentType[];onChange:(value:EntryValue)=>void}){
  const [options,setOptions]=useState<Entry[]>([])
  useEffect(()=>{if(!field.targetContentTypeId)return;const c=new AbortController();getEntries(field.targetContentTypeId,c.signal).then(setOptions).catch(()=>setOptions([]));return()=>c.abort()},[field.targetContentTypeId])
  const target=contentTypes.find(type=>type.id===field.targetContentTypeId)
  return <TextField select label={field.label} value={typeof value==='string'?value:''} onChange={event=>onChange(event.target.value||null)} required={field.required} helperText={`Choose an entry from ${target?.name??'the target type'}`}><MenuItem value="">None</MenuItem>{options.map(entry=><MenuItem key={entry.id} value={entry.id}>{entry.title}</MenuItem>)}</TextField>
}

function toForm(entry: Entry, type: ContentType): EntryForm { return { id: entry.id, title: entry.title, slug: entry.slug, values: initializeEntryValues(type.fields, entry.values), version: entry.version } }
function validateForm(form: EntryForm, type: ContentType): string | null {
  if (!form.title.trim()) return 'Title is required.'
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(form.slug)) return 'Slug must use lowercase letters, numbers and single hyphens.'
  return validateEntryValues(type.fields, form.values)[0] ?? null
}
function errorMessage(reason: unknown, fallback: string): string { return reason instanceof Error ? reason.message : fallback }
