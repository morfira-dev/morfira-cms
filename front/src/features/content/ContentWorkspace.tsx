import { useEffect, useState } from 'react'
import { Alert, Divider, Stack } from '../../ui'
import { ContentTypesWorkspace } from '../contentTypes/ContentTypesWorkspace'
import { getContentTypes, type ContentType } from '../contentTypes/api'
import { EntriesWorkspace } from '../entries/EntriesWorkspace'
import { MediaLibrary } from '../media/MediaLibrary'
import { getMedia, type MediaAsset } from '../media/api'
import { NavigationWorkspace } from '../navigation/NavigationWorkspace'
import { PagesWorkspace } from '../pages/PagesWorkspace'
import { getTaxonomies, type Taxonomy } from '../taxonomy/api'
import { TaxonomyWorkspace } from '../taxonomy/TaxonomyWorkspace'

export function ContentWorkspace() {
  const [assets, setAssets] = useState<MediaAsset[]>([])
  const [loadingMedia, setLoadingMedia] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [contentTypes, setContentTypes] = useState<ContentType[]>([])
  const [loadingContentTypes, setLoadingContentTypes] = useState(true)
  const [taxonomies, setTaxonomies] = useState<Taxonomy[]>([])

  useEffect(() => {
    const controller = new AbortController()

    getMedia(controller.signal)
      .then(setAssets)
      .catch((reason: unknown) => {
        if (reason instanceof DOMException && reason.name === 'AbortError') return
        setError(reason instanceof Error ? reason.message : 'Could not load the media library.')
      })
      .finally(() => setLoadingMedia(false))

    return () => controller.abort()
  }, [])

  useEffect(() => { const controller=new AbortController();getTaxonomies(controller.signal).then(setTaxonomies).catch((reason:unknown)=>{if(!(reason instanceof DOMException&&reason.name==='AbortError'))setError(reason instanceof Error?reason.message:'Could not load taxonomies.')});return()=>controller.abort() }, [])

  useEffect(() => {
    const controller = new AbortController()
    getContentTypes(controller.signal)
      .then(setContentTypes)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : 'Could not load content types.')
      })
      .finally(() => setLoadingContentTypes(false))
    return () => controller.abort()
  }, [])

  return (
    <Stack spacing={5}>
      {error && <Alert severity="error">{error}</Alert>}
      <PagesWorkspace mediaAssets={assets} />
      <Divider />
      <MediaLibrary
        assets={assets}
        loading={loadingMedia}
        onUploaded={(asset) => setAssets((current) => [asset, ...current])}
      />
      <Divider />
      <NavigationWorkspace />
      <Divider />
      <ContentTypesWorkspace contentTypes={contentTypes} loading={loadingContentTypes} onChange={setContentTypes} />
      <Divider />
      <TaxonomyWorkspace taxonomies={taxonomies} onChange={setTaxonomies} />
      <Divider />
      <EntriesWorkspace contentTypes={contentTypes} mediaAssets={assets} taxonomies={taxonomies} />
    </Stack>
  )
}
