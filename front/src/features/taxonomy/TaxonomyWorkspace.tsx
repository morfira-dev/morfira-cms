import { type FormEvent, useState } from 'react'
import { Alert, Button, Card, CardContent, Chip, FormControlLabel, MenuItem, Stack, Switch, TextField, Typography } from '../../ui'
import { createTaxonomy, createTerm, deleteTaxonomy, deleteTerm, type Taxonomy, updateTaxonomy, updateTerm } from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'

export function TaxonomyWorkspace({ taxonomies, onChange }: { taxonomies: Taxonomy[]; onChange: (items: Taxonomy[]) => void }) {
  const { errorMessage, t } = useAdminLocale()
  const [name, setName] = useState(''); const [slug, setSlug] = useState(''); const [hierarchical, setHierarchical] = useState(true)
  const [selected, setSelected] = useState(''); const [editName, setEditName] = useState(''); const [editSlug, setEditSlug] = useState(''); const [editHierarchical, setEditHierarchical] = useState(true)
  const [termName, setTermName] = useState(''); const [termSlug, setTermSlug] = useState(''); const [parentId, setParentId] = useState(''); const [editingTermId, setEditingTermId] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null); const [busy, setBusy] = useState(false)
  const current = taxonomies.find((taxonomy) => taxonomy.id === selected) ?? null

  function selectTaxonomy(id: string) {
    const taxonomy = taxonomies.find((item) => item.id === id)
    setSelected(id); setEditingTermId(null); setTermName(''); setTermSlug(''); setParentId('')
    if (taxonomy) { setEditName(taxonomy.name); setEditSlug(taxonomy.slug); setEditHierarchical(taxonomy.hierarchical) }
  }

  async function addTaxonomy(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError(null)
    try { const item = await createTaxonomy(name, slug, hierarchical); onChange([...taxonomies, item]); selectTaxonomy(item.id); setName(''); setSlug('') }
    catch (reason) { setError(errorMessage(reason, 'Could not create taxonomy.')) }
    finally { setBusy(false) }
  }

  async function saveTaxonomy() {
    if (!current) return; setBusy(true); setError(null)
    try { const updated = await updateTaxonomy(current.id, editName, editSlug, editHierarchical); onChange(taxonomies.map((item) => item.id === updated.id ? updated : item)) }
    catch (reason) { setError(errorMessage(reason, 'Could not update taxonomy.')) }
    finally { setBusy(false) }
  }

  async function removeTaxonomy() {
    if (!current || !window.confirm(t('Delete taxonomy “{{name}}” and its mutable assignments?', { name: current.name }))) return
    setBusy(true); setError(null)
    try { await deleteTaxonomy(current.id); onChange(taxonomies.filter((item) => item.id !== current.id)); setSelected('') }
    catch (reason) { setError(errorMessage(reason, 'Could not delete taxonomy.')) }
    finally { setBusy(false) }
  }

  async function saveTerm(event: FormEvent) {
    event.preventDefault(); if (!current) return; setBusy(true); setError(null)
    try {
      const updated = editingTermId ? await updateTerm(current.id, editingTermId, termName, termSlug, parentId || null) : await createTerm(current.id, termName, termSlug, parentId || null)
      onChange(taxonomies.map((item) => item.id === updated.id ? updated : item)); setEditingTermId(null); setTermName(''); setTermSlug(''); setParentId('')
    } catch (reason) { setError(errorMessage(reason, 'Could not save term.')) }
    finally { setBusy(false) }
  }

  async function removeTerm(termId: string) {
    if (!current || !window.confirm(t('Delete this term and its mutable assignments?'))) return
    setBusy(true); setError(null)
    try { const updated = await deleteTerm(current.id, termId); onChange(taxonomies.map((item) => item.id === updated.id ? updated : item)); if (editingTermId === termId) setEditingTermId(null) }
    catch (reason) { setError(errorMessage(reason, 'Could not delete term.')) }
    finally { setBusy(false) }
  }

  return <Stack spacing={3}>
    <div><Typography component="h1" variant="h1">{t('Taxonomies')}</Typography><Typography color="text.secondary" sx={{ mt: .75 }}>{t('Create reusable categories and tags for structured entries.')}</Typography></div>
    {error && <Alert severity="error">{error}</Alert>}
    <Card variant="outlined"><CardContent component="form" onSubmit={addTaxonomy}><Stack spacing={2}><Typography variant="h6">{t('New taxonomy')}</Typography><Stack direction={{ xs: 'column', md: 'row' }} spacing={2}><TextField label={t('Name')} value={name} onChange={(event) => setName(event.target.value)} required fullWidth/><TextField label={t('Slug')} value={slug} onChange={(event) => setSlug(event.target.value.toLowerCase())} required fullWidth/><FormControlLabel control={<Switch checked={hierarchical} onChange={(event) => setHierarchical(event.target.checked)}/>} label={t('Hierarchical')}/><Button type="submit" variant="contained" disabled={busy}>{t('Create')}</Button></Stack></Stack></CardContent></Card>
    <TextField select label={t('Taxonomy')} value={selected} onChange={(event) => selectTaxonomy(event.target.value)} helperText={t('Choose a taxonomy to manage it')}>{taxonomies.map((taxonomy) => <MenuItem key={taxonomy.id} value={taxonomy.id}>{taxonomy.name} ({taxonomy.slug})</MenuItem>)}</TextField>
    {current && <Card variant="outlined"><CardContent><Stack spacing={3}>
      <Typography variant="h6">{t('Edit taxonomy')}</Typography>
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}><TextField label={t('Name')} value={editName} onChange={(event) => setEditName(event.target.value)} required fullWidth/><TextField label={t('Slug')} value={editSlug} onChange={(event) => setEditSlug(event.target.value.toLowerCase())} required fullWidth/><FormControlLabel control={<Switch checked={editHierarchical} onChange={(event) => { setEditHierarchical(event.target.checked); if (!event.target.checked) setParentId('') }}/>} label={t('Hierarchical')}/><Button variant="contained" disabled={busy} onClick={() => void saveTaxonomy()}>{t('Save')}</Button><Button color="error" variant="outlined" disabled={busy} onClick={() => void removeTaxonomy()}>{t('Delete')}</Button></Stack>
      <Typography variant="h6">{t('Terms')}</Typography>
      <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>{current.terms.map((term) => <Stack key={term.id} direction="row" spacing={.5}><Chip label={term.name} onClick={() => { setEditingTermId(term.id); setTermName(term.name); setTermSlug(term.slug); setParentId(term.parentId ?? '') }}/><Button size="small" color="error" disabled={busy} onClick={() => void removeTerm(term.id)}>{t('Delete')}</Button></Stack>)}</Stack>
      <Stack component="form" onSubmit={saveTerm} direction={{ xs: 'column', md: 'row' }} spacing={2}><TextField label={t('Term name')} value={termName} onChange={(event) => setTermName(event.target.value)} required fullWidth/><TextField label={t('Term slug')} value={termSlug} onChange={(event) => setTermSlug(event.target.value.toLowerCase())} required fullWidth/>{current.hierarchical && <TextField select label={t('Parent')} value={parentId} onChange={(event) => setParentId(event.target.value)} fullWidth><MenuItem value="">{t('None')}</MenuItem>{current.terms.filter((term) => term.id !== editingTermId).map((term) => <MenuItem key={term.id} value={term.id}>{term.name}</MenuItem>)}</TextField>}<Button type="submit" variant="contained" disabled={busy}>{editingTermId ? t('Save term') : t('Add term')}</Button>{editingTermId && <Button onClick={() => { setEditingTermId(null); setTermName(''); setTermSlug(''); setParentId('') }}>{t('Cancel')}</Button>}</Stack>
    </Stack></CardContent></Card>}
  </Stack>
}
