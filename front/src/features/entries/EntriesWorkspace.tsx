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
  deleteEntry,
  type Entry,
  type EntryValue,
  type EntryValues,
  getEntries,
  getEntry,
  getEntryReferences,
  getEntryRevisions,
  publishEntry,
  type EntryReference,
  type EntryRevision,
  restoreEntryRevision,
  unpublishEntry,
  updateEntry,
} from './api'
import { initializeEntryValues, publishedEntryUrl, setEntryValue, validateEntryValues } from './values'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { MessageKey } from '../../i18n/messages'
import { RichTextEditor } from '../richText/RichTextEditor'

interface EntriesWorkspaceProps { contentTypes: ContentType[]; mediaAssets: MediaAsset[]; taxonomies: Taxonomy[] }
interface EntryForm { id: string | null; title: string; slug: string; values: EntryValues; version: number | null }

export function EntriesWorkspace({ contentTypes, mediaAssets, taxonomies }: EntriesWorkspaceProps) {
  const { locale, t } = useAdminLocale()
  const [contentTypeId, setContentTypeId] = useState('')
  const [entries, setEntries] = useState<Entry[]>([])
  const [editing, setEditing] = useState<EntryForm | null>(null)
  const [dirty, setDirty] = useState(false)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [publishedUrl, setPublishedUrl] = useState<string | null>(null)
  const [references, setReferences] = useState<EntryReference[]>([])
  const [revisions, setRevisions] = useState<EntryRevision[]>([])
  const [restoringRevision, setRestoringRevision] = useState<number | null>(null)
  const request = useRef<AbortController | null>(null)
  const skipContentTypeReload = useRef(false)

  const contentType = contentTypes.find((type) => type.id === contentTypeId) ?? null

  useEffect(() => {
    if (skipContentTypeReload.current) {
      skipContentTypeReload.current = false
      return
    }
    if (!contentTypeId) {
      setEntries([])
      setEditing(null)
      setReferences([])
      setRevisions([])
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
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(localizedError(locale, reason, t('Could not load entries.')))
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
      const [entry, incoming, history] = await Promise.all([getEntry(id, controller.signal), getEntryReferences(id, controller.signal), getEntryRevisions(id, controller.signal)])
      setEditing(toForm(entry, contentType))
      setReferences(incoming)
      setRevisions(history)
      setDirty(false)
      setSuccess(null)
      setPublishedUrl(null)
    } catch (reason) {
      if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(localizedError(locale, reason, t('Could not load entry.')))
    } finally {
      if (request.current === controller) setLoading(false)
    }
  }

  function newEntry() {
    if (!contentType) return
    setEditing({ id: null, title: '', slug: '', values: initializeEntryValues(contentType.fields), version: null })
    setReferences([])
    setRevisions([])
    setDirty(true)
    setError(null)
    setSuccess(null)
    setPublishedUrl(null)
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!editing || !contentType) return
    const validation = validateForm(editing, contentType)
    if (validation) { setError(t(validation)); return }
    setSaving(true)
    setError(null)
    try {
      const saved = editing.id === null
        ? await createEntry({ contentTypeId: contentType.id, title: editing.title.trim(), slug: editing.slug, values: editing.values })
        : await updateEntry(editing.id, { title: editing.title.trim(), slug: editing.slug, values: editing.values, version: editing.version! })
      setEditing(toForm(saved, contentType))
      setReferences(await getEntryReferences(saved.id))
      setRevisions(await getEntryRevisions(saved.id))
      setEntries((current) => editing.id === null ? [saved, ...current] : current.map((entry) => entry.id === saved.id ? saved : entry))
      setDirty(false)
      setPublishedUrl(null)
      setSuccess(t('Draft version {{version}} was saved.', { version: saved.version }))
    } catch (reason) {
      setError(`${localizedError(locale, reason, t('Could not save entry.'))} ${t('Reload it if another editor changed it.')}`)
    } finally { setSaving(false) }
  }

  async function publish() {
    if (!editing?.id || editing.version === null || dirty || !contentType) return
    setPublishing(true); setError(null); setSuccess(null)
    try {
      const published = await publishEntry(editing.id, editing.version)
      const url = publishedEntryUrl(published)
      setPublishedUrl(url)
      setSuccess(t('Version {{version}} is published.', { version: published.sourceVersion }))
    } catch (reason) { setError(localizedError(locale, reason, t('Could not publish entry.'))) }
    finally { setPublishing(false) }
  }

  async function unpublish() {
    if (!editing?.id || !window.confirm(t('Unpublish this entry? Its draft and publication history will be preserved.'))) return
    setPublishing(true); setError(null); setSuccess(null)
    try {
      await unpublishEntry(editing.id)
      setPublishedUrl(null)
      setSuccess(t('The entry is no longer publicly available.'))
    } catch (reason) { setError(localizedError(locale, reason, t('Could not unpublish entry.'))) }
    finally { setPublishing(false) }
  }

  async function remove() {
    if (!editing?.id || !window.confirm(t('Permanently delete this entry draft and all revisions? Published snapshots will be retained.'))) return
    setDeleting(true); setError(null); setSuccess(null)
    try {
      await deleteEntry(editing.id)
      setEntries((current) => current.filter((entry) => entry.id !== editing.id))
      setEditing(null)
      setPublishedUrl(null)
      setSuccess(t('The entry draft was permanently deleted.'))
    } catch (reason) { setError(localizedError(locale, reason, t('Could not delete entry.'))) }
    finally { setDeleting(false) }
  }

  function changeValue(key: string, value: EntryValue) {
    if (!editing) return
    setEditing({ ...editing, values: setEntryValue(editing.values, key, value) })
    setDirty(true); setSuccess(null); setPublishedUrl(null)
  }

  async function openReference(reference: EntryReference) {
    const type = contentTypes.find((item) => item.id === reference.contentTypeId)
    if (!type) return
    setLoading(true); setError(null)
    try {
      const [entry, typeEntries, incoming, history] = await Promise.all([getEntry(reference.id), getEntries(reference.contentTypeId), getEntryReferences(reference.id), getEntryRevisions(reference.id)])
      if(reference.contentTypeId!==contentTypeId)skipContentTypeReload.current=true
      setContentTypeId(reference.contentTypeId)
      setEntries(typeEntries)
      setEditing(toForm(entry, type))
      setReferences(incoming)
      setRevisions(history)
      setDirty(false); setSuccess(null); setPublishedUrl(null)
    } catch (reason) { setError(localizedError(locale, reason, t('Could not load entry.'))) }
    finally { setLoading(false) }
  }

  async function restoreRevision(revision: number) {
    if (!editing?.id || editing.version===null || dirty || !contentType || !window.confirm(t('Restore revision {{revision}} as a new draft revision?', { revision }))) return
    setRestoringRevision(revision); setError(null); setSuccess(null)
    try {
      const restored=await restoreEntryRevision(editing.id,revision,editing.version)
      setEditing(toForm(restored,contentType));setEntries(current=>current.map(entry=>entry.id===restored.id?restored:entry));setRevisions(await getEntryRevisions(restored.id));setPublishedUrl(null);setDirty(false)
      setSuccess(t('Revision {{source}} was restored as revision {{target}}.',{source:revision,target:restored.version}))
    }catch(reason){setError(localizedError(locale,reason,t('Could not restore the revision.')))}finally{setRestoringRevision(null)}
  }

  return (
    <Stack spacing={3}>
      <div><Typography component="h1" variant="h1">{t('Entries')}</Typography><Typography color="text.secondary" sx={{ mt: 0.75 }}>{t('Create structured content with a form generated from its schema.')}</Typography></div>
      {error && <Alert severity="error">{error}</Alert>}
      {success && <Alert severity="success">{success}</Alert>}

      <TextField select label={t('Content type')} value={contentTypeId} onChange={(event) => setContentTypeId(event.target.value)} helperText={t('Choose the schema for these entries')}>
        {contentTypes.map((type) => <MenuItem key={type.id} value={type.id}>{type.name} ({type.apiName})</MenuItem>)}
      </TextField>

      {!contentType ? <Alert severity="info">{t('Create and select a content type first.')}</Alert> : (
        <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: '280px minmax(0, 1fr)' }, gap: 3 }}>
          <Card variant="outlined" sx={{ alignSelf: 'start' }}><CardContent>
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}><Typography variant="h6">{contentType.name}</Typography><Button size="small" variant="contained" onClick={newEntry}>{t('New')}</Button></Stack>
            {loading && !editing ? <Stack sx={{ py: 4, alignItems: 'center' }}><CircularProgress size={26} /></Stack> : (
              <Stack spacing={1} sx={{ mt: 2 }}>
                {entries.map((entry) => <Button key={entry.id} variant={editing?.id === entry.id ? 'contained' : 'outlined'} onClick={() => void selectEntry(entry.id)} sx={{ justifyContent: 'space-between' }}><span>{entry.title}</span><Chip label={`v${entry.version}`} size="small" /></Button>)}
                {entries.length === 0 && <Typography color="text.secondary">{t('No entries yet.')}</Typography>}
              </Stack>
            )}
          </CardContent></Card>

          {loading && editing ? <Stack sx={{ minHeight: 240, alignItems: 'center', justifyContent: 'center' }}><CircularProgress /></Stack> : !editing ? <Alert severity="info">{t('Create or select an entry.')}</Alert> : (
            <Card variant="outlined"><CardContent component="form" onSubmit={save}>
              <Stack spacing={3}>
                <Stack direction={{ xs: 'column', md: 'row' }} sx={{ justifyContent: 'space-between', gap: 2 }}>
                  <div><Typography variant="h5">{editing.id ? t('Edit entry') : t('New entry')}</Typography><Typography color="text.secondary">{editing.version ? t('Draft version {{version}}', { version: editing.version }) : t('Not saved yet')}</Typography></div>
                  <Stack direction="row" spacing={1}>
                    {publishedUrl && <Button component="a" href={publishedUrl} target="_blank" variant="outlined">{t('Open published API')}</Button>}
                    <Button type="submit" variant="contained" disabled={!dirty || saving}>{saving ? t('Saving…') : t('Save')}</Button>
                    <Button color="success" variant="contained" disabled={!editing.id || dirty || publishing} onClick={() => void publish()}>{publishing ? t('Publishing…') : t('Publish')}</Button>
                    <Button color="warning" variant="outlined" disabled={!editing.id || publishing} onClick={() => void unpublish()}>{t('Unpublish')}</Button>
                    <Button color="error" variant="outlined" disabled={!editing.id || publishing || deleting} onClick={() => void remove()}>{deleting ? t('Deleting…') : t('Delete')}</Button>
                  </Stack>
                </Stack>
                {dirty && editing.id && <Alert severity="warning">{t('Save this version before publishing it.')}</Alert>}
                {editing.id && editing.version !== null && <PublicationScheduler target="entry" targetId={editing.id} sourceVersion={editing.version} dirty={dirty} />}
                {editing.id && <EntryTaxonomyAssignments entryId={editing.id} taxonomies={taxonomies} />}
                {editing.id && <Card variant="outlined"><CardContent><Typography variant="h6">{t('Referenced by')}</Typography>{references.length===0?<Typography color="text.secondary" sx={{mt:1}}>{t('No other drafts reference this entry.')}</Typography>:<Stack spacing={1} sx={{mt:1}}>{references.map(reference=><Button key={`${reference.id}:${reference.fieldKey}`} variant="outlined" onClick={()=>void openReference(reference)} sx={{justifyContent:'space-between'}}><span>{reference.title}</span><Chip size="small" label={reference.fieldKey}/></Button>)}</Stack>}</CardContent></Card>}
                {editing.id && <Card variant="outlined"><CardContent><Typography variant="h6">{t('Revision history')}</Typography><Typography variant="body2" color="text.secondary" sx={{mt:.5,mb:2}}>{t('Restoring creates a new revision and never changes the published entry.')}</Typography><Stack spacing={1}>{revisions.map(revision=><Stack key={revision.revision} direction={{xs:'column',sm:'row'}} sx={{justifyContent:'space-between',alignItems:{sm:'center'},gap:1,py:1,borderTop:1,borderColor:'divider'}}><div><Typography sx={{fontWeight:700}}>{t('Revision {{revision}} · {{title}}',{revision:revision.revision,title:revision.title})}</Typography><Typography variant="body2" color="text.secondary">{new Date(revision.createdAt).toLocaleString(locale)} · /{revision.slug}</Typography></div><Button size="small" variant="outlined" disabled={dirty||revision.revision===editing.version||restoringRevision!==null} onClick={()=>void restoreRevision(revision.revision)}>{restoringRevision===revision.revision?t('Restoring…'):t('Restore')}</Button></Stack>)}</Stack></CardContent></Card>}
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField label={t('Title')} value={editing.title} onChange={(event) => { setEditing({ ...editing, title: event.target.value }); setDirty(true); setPublishedUrl(null) }} fullWidth required />
                  <TextField label={t('Slug')} value={editing.slug} onChange={(event) => { setEditing({ ...editing, slug: event.target.value.toLowerCase() }); setDirty(true); setPublishedUrl(null) }} fullWidth required helperText={t('Lowercase letters, numbers and hyphens')} />
                </Stack>
                {contentType.fields.length === 0 && <Alert severity="info">{t('This schema has no custom fields.')}</Alert>}
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
  const { t } = useAdminLocale()
  if (field.type === 'boolean') return <FormControlLabel control={<Switch checked={value === true} onChange={(event) => onChange(event.target.checked)} />} label={`${field.label}${field.required ? ' *' : ''}`} />
  if (field.type === 'media') return <TextField select label={field.label} value={typeof value === 'string' ? value : ''} onChange={(event) => onChange(event.target.value || null)} required={field.required} helperText={t('Choose an image from the media library')}><MenuItem value="">{t('None')}</MenuItem>{mediaAssets.map((asset) => <MenuItem key={asset.id} value={asset.id}>{asset.originalName}</MenuItem>)}</TextField>
  if (field.type === 'number') return <TextField type="number" label={field.label} value={typeof value === 'number' ? value : ''} onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))} required={field.required} />
  if (field.type === 'richText') return <RichTextEditor value={typeof value === 'string' ? value : ''} onChange={(html) => onChange(html)} label={field.label} required={field.required} />
  if(field.type==='relation') return <RelationField field={field} value={value} contentTypes={contentTypes} onChange={onChange}/>
  return <TextField type={field.type === 'date' ? 'date' : 'text'} label={field.label} value={typeof value === 'string' ? value : ''} onChange={(event) => onChange(event.target.value || null)} required={field.required} multiline={field.type === 'textarea'} minRows={field.type === 'textarea' ? 5 : undefined} slotProps={field.type === 'date' ? { inputLabel: { shrink: true } } : undefined} />
}

function RelationField({field,value,contentTypes,onChange}:{field:ContentField;value:EntryValue;contentTypes:ContentType[];onChange:(value:EntryValue)=>void}){
  const { t } = useAdminLocale()
  const [options,setOptions]=useState<Entry[]>([])
  useEffect(()=>{if(!field.targetContentTypeId)return;const c=new AbortController();getEntries(field.targetContentTypeId,c.signal).then(setOptions).catch(()=>setOptions([]));return()=>c.abort()},[field.targetContentTypeId])
  const target=contentTypes.find(type=>type.id===field.targetContentTypeId)
  if(field.multiple){
    const selected=Array.isArray(value)?value:[]
    const labels=new Map(options.map(entry=>[entry.id,entry.title]))
    const move=(index:number,offset:-1|1)=>{const destination=index+offset;if(destination<0||destination>=selected.length)return;const next=[...selected];[next[index],next[destination]]=[next[destination],next[index]];onChange(next)}
    return <Stack spacing={1.5}>
      <TextField select label={field.label} value={selected} onChange={event=>onChange(typeof event.target.value==='string'?event.target.value.split(','):event.target.value as string[])} required={field.required} helperText={t('Choose entries from {{type}}', { type: target?.name ?? t('the target type') })} slotProps={{select:{multiple:true,renderValue:(ids:unknown)=>(Array.isArray(ids)?ids.filter((id):id is string=>typeof id==='string'):[]).map(id=>labels.get(id)??id).join(', ')}}}>
        {options.map(entry=><MenuItem key={entry.id} value={entry.id}>{entry.title}</MenuItem>)}
      </TextField>
      {selected.map((id,index)=><Stack key={id} direction="row" spacing={1} sx={{alignItems:'center'}}><Typography sx={{flex:1}}>{index+1}. {labels.get(id)??id}</Typography><Button size="small" disabled={index===0} onClick={()=>move(index,-1)}>{t('Up')}</Button><Button size="small" disabled={index===selected.length-1} onClick={()=>move(index,1)}>{t('Down')}</Button><Button size="small" color="error" onClick={()=>onChange(selected.filter(item=>item!==id))}>{t('Remove')}</Button></Stack>)}
    </Stack>
  }
  return <TextField select label={field.label} value={typeof value==='string'?value:''} onChange={event=>onChange(event.target.value||null)} required={field.required} helperText={t('Choose an entry from {{type}}', { type: target?.name ?? t('the target type') })}><MenuItem value="">{t('None')}</MenuItem>{options.map(entry=><MenuItem key={entry.id} value={entry.id}>{entry.title}</MenuItem>)}</TextField>
}

function toForm(entry: Entry, type: ContentType): EntryForm { return { id: entry.id, title: entry.title, slug: entry.slug, values: initializeEntryValues(type.fields, entry.values), version: entry.version } }
function validateForm(form: EntryForm, type: ContentType): MessageKey | null {
  if (!form.title.trim()) return 'Title is required.'
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(form.slug)) return 'Slug must use lowercase letters, numbers and single hyphens.'
  return validateEntryValues(type.fields, form.values).length > 0 ? 'Check the required custom fields.' : null
}
function localizedError(locale: string, reason: unknown, fallback: string): string { return locale === 'en' && reason instanceof Error ? reason.message : fallback }
