import { useEffect, useState } from 'react'
import type { AdminRouteId } from '../../routing/adminRoutes'
import { Alert, Card, CardContent, Stack, Typography } from '../../ui'
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

interface ContentWorkspaceProps {
  route: AdminRouteId
  isOwner: boolean
  currentUserId: string
}

export function ContentWorkspace({ route, isOwner, currentUserId }: ContentWorkspaceProps) {
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
  else if (route === 'settings') workspace = <Placeholder title="Settings" text="Site-level settings will be introduced through a dedicated contract." />
  else workspace = <Dashboard />

  return <Stack spacing={3}>{error && <Alert severity="error">{error}</Alert>}{workspace}</Stack>
}

function Dashboard() {
  return <Stack spacing={3}>
    <div><Typography component="h1" variant="h1">Dashboard</Typography><Typography color="text.secondary" sx={{ mt: .75 }}>Morfira keeps authoring and immutable delivery in one focused CMS.</Typography></div>
    <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
      <StatusCard title="Complete CMS" text="Twig pages, navigation, SEO, media and taxonomy archives." />
      <StatusCard title="Headless API" text="Versioned OpenAPI delivery backed by the same publication snapshots." />
      <StatusCard title="Safe publishing" text="Optimistic drafts, exact revisions, scheduling and direct redirects." />
    </Stack>
  </Stack>
}

function StatusCard({ title, text }: { title: string; text: string }) {
  return <Card variant="outlined" sx={{ flex: 1 }}><CardContent><Typography variant="h6">{title}</Typography><Typography color="text.secondary" sx={{ mt: 1 }}>{text}</Typography></CardContent></Card>
}

function Placeholder({ title, text }: { title: string; text: string }) {
  return <Stack spacing={1}><Typography component="h1" variant="h1">{title}</Typography><Typography color="text.secondary">{text}</Typography></Stack>
}
