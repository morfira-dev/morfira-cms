import { useEffect, useState } from 'react'
import type { AdminRouteId } from '../../routing/adminRoutes'
import { Alert, Stack } from '../../ui'
import { ContentTypesWorkspace } from '../contentTypes/ContentTypesWorkspace'
import { getContentTypes, type ContentType } from '../contentTypes/api'
import { EntriesWorkspace } from '../entries/EntriesWorkspace'
import { MediaLibrary } from '../media/MediaLibrary'
import { getMedia, type MediaAsset } from '../media/api'
import { NavigationWorkspace } from '../navigation/NavigationWorkspace'
import { PagesWorkspace } from '../pages/PagesWorkspace'
import { getTaxonomies, type Taxonomy } from '../taxonomy/api'
import { TaxonomyWorkspace } from '../taxonomy/TaxonomyWorkspace'
import { UsersWorkspace } from '../users/UsersWorkspace'
import { SettingsWorkspace } from '../settings/SettingsWorkspace'
import { DashboardWorkspace } from '../dashboard/DashboardWorkspace'

interface ContentWorkspaceProps {
  route: AdminRouteId
  isOwner: boolean
  currentUserId: string
  onNavigate: (path: string) => void
  onSessionEnded: () => void
}

export function ContentWorkspace({ route, isOwner, currentUserId, onNavigate, onSessionEnded }: ContentWorkspaceProps) {
  const [assets, setAssets] = useState<MediaAsset[]>([])
  const [loadingMedia, setLoadingMedia] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [contentTypes, setContentTypes] = useState<ContentType[]>([])
  const [loadingContentTypes, setLoadingContentTypes] = useState(true)
  const [taxonomies, setTaxonomies] = useState<Taxonomy[]>([])

  useEffect(() => {
    if (!['pages', 'media', 'entries'].includes(route)) return
    const controller = new AbortController()
    setLoadingMedia(true)
    getMedia(controller.signal)
      .then(setAssets)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : 'Could not load the media library.')
      })
      .finally(() => setLoadingMedia(false))
    return () => controller.abort()
  }, [route])

  useEffect(() => {
    if (!['taxonomies', 'entries'].includes(route)) return
    const controller = new AbortController()
    getTaxonomies(controller.signal)
      .then(setTaxonomies)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : 'Could not load taxonomies.')
      })
    return () => controller.abort()
  }, [route])

  useEffect(() => {
    if (!['content-types', 'entries'].includes(route)) return
    const controller = new AbortController()
    setLoadingContentTypes(true)
    getContentTypes(controller.signal)
      .then(setContentTypes)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : 'Could not load content types.')
      })
      .finally(() => setLoadingContentTypes(false))
    return () => controller.abort()
  }, [route])

  let workspace
  if (route === 'pages') workspace = <PagesWorkspace mediaAssets={assets} />
  else if (route === 'media') workspace = <MediaLibrary assets={assets} loading={loadingMedia} onUploaded={(asset) => setAssets((current) => [asset, ...current])} />
  else if (route === 'navigation') workspace = <NavigationWorkspace />
  else if (route === 'content-types') workspace = <ContentTypesWorkspace contentTypes={contentTypes} loading={loadingContentTypes} onChange={setContentTypes} />
  else if (route === 'taxonomies') workspace = <TaxonomyWorkspace taxonomies={taxonomies} onChange={setTaxonomies} />
  else if (route === 'entries') workspace = <EntriesWorkspace contentTypes={contentTypes} mediaAssets={assets} taxonomies={taxonomies} />
  else if (route === 'users') workspace = isOwner ? <UsersWorkspace currentUserId={currentUserId} /> : <Alert severity="error">Owner access is required.</Alert>
  else if (route === 'settings') workspace = <SettingsWorkspace isOwner={isOwner} onSessionEnded={onSessionEnded} />
  else workspace = <DashboardWorkspace onNavigate={onNavigate} />

  return <Stack spacing={3}>{error && <Alert severity="error">{error}</Alert>}{workspace}</Stack>
}
