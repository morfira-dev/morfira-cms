import { useEffect, useState } from 'react'
import { Alert, Button, Chip, Stack, Typography } from '../../ui'
import { getEntryTerms, putEntryTerms, type Taxonomy } from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { MessageKey } from '../../i18n/messages'
import { scheduleAbortableLoad } from '../../utils/scheduleAbortableLoad'

export function EntryTaxonomyAssignments({entryId,taxonomies}:{entryId:string;taxonomies:Taxonomy[]}){
  const {errorMessage,t}=useAdminLocale()
  const [selected, setSelected] = useState<string[]>([])
  const [saved, setSaved] = useState<string[]>([])
  const [failure, setFailure] = useState<{ reason: unknown; message: MessageKey } | null>(null)
  const [busy, setBusy] = useState(true)
  const error = failure && errorMessage(failure.reason, failure.message)

  useEffect(() => scheduleAbortableLoad((signal) => {
    setBusy(true)
    setFailure(null)
    setSelected([])
    setSaved([])
    getEntryTerms(entryId, signal)
      .then((items) => {
        if (signal.aborted) return
        const ids = items.map((item) => item.id)
        setSelected(ids)
        setSaved(ids)
      })
      .catch((reason: unknown) => {
        if (!signal.aborted) setFailure({ reason, message: 'Could not load terms.' })
      })
      .finally(() => { if (!signal.aborted) setBusy(false) })
  }), [entryId])
  function toggle(id:string){setSelected(current=>current.includes(id)?current.filter(v=>v!==id):[...current,id])}
  async function save() {
    setBusy(true)
    setFailure(null)
    try {
      const items = await putEntryTerms(entryId, selected)
      const ids = items.map((item) => item.id)
      setSelected(ids)
      setSaved(ids)
    } catch (reason) {
      setFailure({ reason, message: 'Could not save terms.' })
    } finally {
      setBusy(false)
    }
  }
  const dirty=JSON.stringify([...selected].sort())!==JSON.stringify([...saved].sort())
  return <Stack spacing={1.5} sx={{p:2,border:1,borderColor:'divider',borderRadius:1}}><Typography variant="subtitle1" sx={{fontWeight:700}}>{t('Categories and tags')}</Typography>{error&&<Alert severity="error">{error}</Alert>}{taxonomies.map(taxonomy=><Stack key={taxonomy.id} direction="row" spacing={1} sx={{alignItems:'center',flexWrap:'wrap'}}><Typography variant="body2" sx={{minWidth:120}}>{taxonomy.name}</Typography>{taxonomy.terms.map(term=><Chip key={term.id} label={term.name} color={selected.includes(term.id)?'primary':'default'} variant={selected.includes(term.id)?'filled':'outlined'} onClick={()=>toggle(term.id)}/>)}</Stack>)}<Button type="button" variant="outlined" disabled={busy||!dirty} onClick={()=>void save()} sx={{alignSelf:'flex-start'}}>{t('Save terms')}</Button><Typography variant="caption" color="text.secondary">{t('Republish the entry to expose assignment changes publicly.')}</Typography></Stack>
}
