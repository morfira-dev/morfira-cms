import { type FormEvent, useRef, useState } from 'react'
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
import {
  createContentType,
  getContentType,
  type ContentField,
  type ContentType,
  updateContentType,
} from './api'
import { contentFieldTypes, createContentField, moveContentField, removeContentField, updateContentField } from './fields'

interface ContentTypesWorkspaceProps {
  contentTypes: ContentType[]
  loading: boolean
  onChange: (types: ContentType[]) => void
}

export function ContentTypesWorkspace({ contentTypes, loading, onChange }: ContentTypesWorkspaceProps) {
  const [editing, setEditing] = useState<ContentType | null>(null)
  const [dirty, setDirty] = useState(false)
  const [name, setName] = useState('')
  const [apiName, setApiName] = useState('')
  const [busy, setBusy] = useState<'create' | 'load' | 'save' | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const selectionRequest = useRef<AbortController | null>(null)

  async function selectType(id: string) {
    selectionRequest.current?.abort()
    const controller = new AbortController()
    selectionRequest.current = controller
    setBusy('load')
    setError(null)
    setSuccess(null)
    try {
      setEditing(structuredClone(await getContentType(id, controller.signal)))
      setDirty(false)
    } catch (reason) {
      if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(errorMessage(reason, 'Could not load schema.'))
    } finally {
      if (selectionRequest.current === controller) setBusy(null)
    }
  }

  async function create(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!name.trim() || !validApiName(apiName)) {
      setError('Enter a name and an API name such as articles or team-members.')
      return
    }
    setBusy('create')
    setError(null)
    try {
      const created = await createContentType({ name: name.trim(), apiName, fields: [] })
      onChange([created, ...contentTypes])
      setEditing(structuredClone(created))
      setDirty(false)
      setName('')
      setApiName('')
      setSuccess(`Content type “${created.name}” was created.`)
    } catch (reason) {
      setError(errorMessage(reason, 'Could not create content type.'))
    } finally {
      setBusy(null)
    }
  }

  function changeFields(fields: ContentField[]) {
    if (!editing) return
    setEditing({ ...editing, fields })
    setDirty(true)
    setSuccess(null)
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!editing) return
    const validation = validateSchema(editing)
    if (validation) {
      setError(validation)
      return
    }
    setBusy('save')
    setError(null)
    try {
      const saved = await updateContentType(editing.id, {
        name: editing.name.trim(),
        apiName: editing.apiName,
        fields: editing.fields,
        version: editing.version,
      })
      setEditing(structuredClone(saved))
      onChange(contentTypes.map((type) => type.id === saved.id ? saved : type))
      setDirty(false)
      setSuccess(`Schema version ${saved.version} was saved.`)
    } catch (reason) {
      setError(`${errorMessage(reason, 'Could not save schema.')} Reload it if another editor changed it.`)
    } finally {
      setBusy(null)
    }
  }

  return (
    <Stack spacing={3}>
      <div>
        <Typography component="h1" variant="h1">Content types</Typography>
        <Typography color="text.secondary" sx={{ mt: 0.75 }}>Define reusable schemas without writing code or JSON.</Typography>
      </div>
      {error && <Alert severity="error">{error}</Alert>}
      {success && <Alert severity="success">{success}</Alert>}

      <Card variant="outlined"><CardContent>
        <Stack component="form" direction={{ xs: 'column', md: 'row' }} spacing={2} onSubmit={create}>
          <TextField label="Name" value={name} onChange={(event) => setName(event.target.value)} required fullWidth />
          <TextField
            label="API name"
            value={apiName}
            onChange={(event) => setApiName(event.target.value.toLowerCase())}
            required
            fullWidth
            helperText="Lowercase, for example: articles"
            slotProps={{ htmlInput: { pattern: '[a-z][a-z0-9]*(?:-[a-z0-9]+)*', maxLength: 120 } }}
          />
          <Button type="submit" variant="contained" disabled={busy === 'create'} sx={{ minWidth: 150, alignSelf: 'flex-start' }}>
            {busy === 'create' ? 'Creating…' : 'Create type'}
          </Button>
        </Stack>
      </CardContent></Card>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', lg: '280px minmax(0, 1fr)' }, gap: 3 }}>
        <Card variant="outlined" sx={{ alignSelf: 'start' }}><CardContent>
          <Typography variant="h6">Schemas</Typography>
          {loading ? <Stack sx={{ py: 4, alignItems: 'center' }}><CircularProgress size={26} /></Stack> : (
            <Stack spacing={1} sx={{ mt: 2 }}>
              {contentTypes.map((type) => (
                <Button
                  key={type.id}
                  variant={editing?.id === type.id ? 'contained' : 'outlined'}
                  onClick={() => void selectType(type.id)}
                  sx={{ justifyContent: 'space-between' }}
                >
                  <span>{type.name}</span><Chip label={`v${type.version}`} size="small" />
                </Button>
              ))}
              {contentTypes.length === 0 && <Typography color="text.secondary">No content types yet.</Typography>}
            </Stack>
          )}
        </CardContent></Card>

        {busy === 'load' ? <Stack sx={{ minHeight: 240, alignItems: 'center', justifyContent: 'center' }}><CircularProgress /></Stack> : !editing ? (
          <Alert severity="info">Create or select a content type to edit its schema.</Alert>
        ) : (
          <Card variant="outlined"><CardContent component="form" onSubmit={save}>
            <Stack spacing={3}>
              <Stack direction={{ xs: 'column', md: 'row' }} sx={{ justifyContent: 'space-between', gap: 2 }}>
                <div><Typography variant="h5">Edit schema</Typography><Typography color="text.secondary">Version {editing.version} · {editing.fields.length}/50 fields</Typography></div>
                <Button type="submit" variant="contained" disabled={!dirty || busy === 'save'}>{busy === 'save' ? 'Saving…' : 'Save schema'}</Button>
              </Stack>
              {dirty && <Alert severity="warning">This schema has unsaved changes.</Alert>}
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                <TextField label="Name" value={editing.name} onChange={(event) => { setEditing({ ...editing, name: event.target.value }); setDirty(true) }} fullWidth required />
                <TextField label="API name" value={editing.apiName} onChange={(event) => { setEditing({ ...editing, apiName: event.target.value.toLowerCase() }); setDirty(true) }} fullWidth required />
              </Stack>
              <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                <Typography variant="h6">Fields</Typography>
                <Button variant="outlined" disabled={editing.fields.length >= 50} onClick={() => changeFields([...editing.fields, createContentField(crypto.randomUUID())])}>Add field</Button>
              </Stack>
              {editing.fields.length === 0 && <Alert severity="info">Add the first field to this schema.</Alert>}
              {editing.fields.map((field, index) => (
                <Card key={field.id} variant="outlined"><CardContent>
                  <Stack spacing={2}>
                    <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                      <Typography sx={{ fontWeight: 700 }}>{index + 1}. {field.label}</Typography>
                      <Stack direction="row" spacing={0.5}>
                        <Button size="small" disabled={index === 0} onClick={() => changeFields(moveContentField(editing.fields, field.id, -1))}>Up</Button>
                        <Button size="small" disabled={index === editing.fields.length - 1} onClick={() => changeFields(moveContentField(editing.fields, field.id, 1))}>Down</Button>
                        <Button size="small" color="error" onClick={() => changeFields(removeContentField(editing.fields, field.id))}>Remove</Button>
                      </Stack>
                    </Stack>
                    <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                      <TextField label="Label" value={field.label} onChange={(event) => changeFields(updateContentField(editing.fields, field.id, { label: event.target.value }))} required fullWidth />
                      <TextField label="Key" value={field.key} onChange={(event) => changeFields(updateContentField(editing.fields, field.id, { key: event.target.value }))} required fullWidth helperText="camelCase, for example: heroImage" />
                      <TextField select label="Type" value={field.type} onChange={(event) => changeFields(updateContentField(editing.fields, field.id, { type: event.target.value as ContentField['type'] }))} fullWidth>
                        {contentFieldTypes.map((type) => <MenuItem key={type} value={type}>{type}</MenuItem>)}
                      </TextField>
                      <FormControlLabel control={<Switch checked={field.required} onChange={(event) => changeFields(updateContentField(editing.fields, field.id, { required: event.target.checked }))} />} label="Required" />
                    </Stack>
                  </Stack>
                </CardContent></Card>
              ))}
            </Stack>
          </CardContent></Card>
        )}
      </Box>
    </Stack>
  )
}

function validateSchema(type: ContentType): string | null {
  if (!type.name.trim() || !validApiName(type.apiName)) return 'Name and a valid lowercase API name are required.'
  if (type.fields.length > 50) return 'A content type can contain at most 50 fields.'
  const keys = new Set<string>()
  const ids = new Set<string>()
  for (const field of type.fields) {
    if (!field.label.trim() || !/^[a-z][a-zA-Z0-9]*$/.test(field.key)) return 'Every field needs a label and a unique camelCase key.'
    if (keys.has(field.key) || ids.has(field.id)) return 'Field keys and identifiers must be unique.'
    keys.add(field.key); ids.add(field.id)
  }
  return null
}

function validApiName(value: string): boolean { return /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/.test(value) }
function errorMessage(reason: unknown, fallback: string): string { return reason instanceof Error ? reason.message : fallback }
