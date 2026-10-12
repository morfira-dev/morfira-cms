import { useEffect, useState } from 'react'
import { Alert, Button, Chip, Stack, Typography } from '../../ui'
import { getEntryTerms, putEntryTerms, type Taxonomy } from './api'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'

export function EntryTaxonomyAssignments({entryId,taxonomies}:{entryId:string;taxonomies:Taxonomy[]}){
  const {t}=useAdminLocale()
  const [selected,setSelected]=useState<string[]>([]);const [saved,setSaved]=useState<string[]>([]);const [error,setError]=useState<string|null>(null);const [busy,setBusy]=useState(true)
  useEffect(()=>{const c=new AbortController();setBusy(true);getEntryTerms(entryId,c.signal).then(items=>{const ids=items.map(i=>i.id);setSelected(ids);setSaved(ids)}).catch(r=>{if(!(r instanceof DOMException&&r.name==='AbortError'))setError(r instanceof Error?r.message:t('Could not load terms.'))}).finally(()=>setBusy(false));return()=>c.abort()},[entryId,t])
  function toggle(id:string){setSelected(current=>current.includes(id)?current.filter(v=>v!==id):[...current,id])}
  async function save(){setBusy(true);setError(null);try{const items=await putEntryTerms(entryId,selected);const ids=items.map(i=>i.id);setSelected(ids);setSaved(ids)}catch(r){setError(r instanceof Error?r.message:t('Could not save terms.'))}finally{setBusy(false)}}
  const dirty=JSON.stringify([...selected].sort())!==JSON.stringify([...saved].sort())
  return <Stack spacing={1.5} sx={{p:2,border:1,borderColor:'divider',borderRadius:1}}><Typography variant="subtitle1" sx={{fontWeight:700}}>{t('Categories and tags')}</Typography>{error&&<Alert severity="error">{error}</Alert>}{taxonomies.map(taxonomy=><Stack key={taxonomy.id} direction="row" spacing={1} sx={{alignItems:'center',flexWrap:'wrap'}}><Typography variant="body2" sx={{minWidth:120}}>{taxonomy.name}</Typography>{taxonomy.terms.map(term=><Chip key={term.id} label={term.name} color={selected.includes(term.id)?'primary':'default'} variant={selected.includes(term.id)?'filled':'outlined'} onClick={()=>toggle(term.id)}/>)}</Stack>)}<Button type="button" variant="outlined" disabled={busy||!dirty} onClick={()=>void save()} sx={{alignSelf:'flex-start'}}>{t('Save terms')}</Button><Typography variant="caption" color="text.secondary">{t('Republish the entry to expose assignment changes publicly.')}</Typography></Stack>
}
