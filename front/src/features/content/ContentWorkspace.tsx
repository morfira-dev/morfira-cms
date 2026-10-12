import { useEffect, useState } from 'react'
import type { AdminRouteId } from '../../routing/adminRoutes'
import { Alert, Stack } from '../../ui'
import { ContentTypesWorkspace } from '../contentTypes/ContentTypesWorkspace'
import { getContentTypes, type ContentType } from '../contentTypes/api'
import { EntriesWorkspace } from '../entries/EntriesWorkspace'
import { MediaLibrary } from '../media/MediaLibrary'
import { getMedia, type MediaAsset } from '../media/api'
import { NavigationWorkspace } from '../navigation/NavigationWorkspace'
import { getNavigationMenus, type NavigationMenu } from '../navigation/api'
import { PagesWorkspace } from '../pages/PagesWorkspace'
import { getTaxonomies, type Taxonomy } from '../taxonomy/api'
import { TaxonomyWorkspace } from '../taxonomy/TaxonomyWorkspace'
import { UsersWorkspace } from '../users/UsersWorkspace'
import { SettingsWorkspace } from '../settings/SettingsWorkspace'
import { DashboardWorkspace } from '../dashboard/DashboardWorkspace'
import { ExtensionsWorkspace } from '../extensions/ExtensionsWorkspace'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { AdminLocale } from '../../i18n/AdminLocaleProvider'

interface ContentWorkspaceProps {
  route: AdminRouteId
  isOwner: boolean
  currentUserId: string
  currentAdminLocale: AdminLocale
  onAdminLocaleChange: (locale: AdminLocale) => Promise<void>
  onNavigate: (path: string) => void
  onSessionEnded: () => void
}

export function ContentWorkspace({ route, isOwner, currentUserId, currentAdminLocale, onAdminLocaleChange, onNavigate, onSessionEnded }: ContentWorkspaceProps) {
  const { t } = useAdminLocale()
  const [assets, setAssets] = useState<MediaAsset[]>([])
  const [loadingMedia, setLoadingMedia] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [contentTypes, setContentTypes] = useState<ContentType[]>([])
  const [loadingContentTypes, setLoadingContentTypes] = useState(true)
  const [taxonomies, setTaxonomies] = useState<Taxonomy[]>([])
  const [navigationMenus, setNavigationMenus] = useState<NavigationMenu[]>([])

  useEffect(() => {
    if (!['pages', 'media', 'entries'].includes(route)) return
    const controller = new AbortController()
    setLoadingMedia(true)
    getMedia(controller.signal)
      .then(setAssets)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : t('Could not load the media library.'))
      })
      .finally(() => setLoadingMedia(false))
    return () => controller.abort()
  }, [route, t])

  useEffect(() => {
    if (route !== 'pages') return
    const controller = new AbortController()
    getNavigationMenus(controller.signal)
      .then(setNavigationMenus)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : t('Could not load navigation choices.'))
      })
    return () => controller.abort()
  }, [route, t])

  useEffect(() => {
    if (!['taxonomies', 'entries'].includes(route)) return
    const controller = new AbortController()
    getTaxonomies(controller.signal)
      .then(setTaxonomies)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : t('Could not load taxonomies.'))
      })
    return () => controller.abort()
  }, [route, t])

  useEffect(() => {
    if (!['content-types', 'entries'].includes(route)) return
    const controller = new AbortController()
    setLoadingContentTypes(true)
    getContentTypes(controller.signal)
      .then(setContentTypes)
      .catch((reason: unknown) => {
        if (!(reason instanceof DOMException && reason.name === 'AbortError')) setError(reason instanceof Error ? reason.message : t('Could not load content types.'))
      })
      .finally(() => setLoadingContentTypes(false))
    return () => controller.abort()
  }, [route, t])

  let workspace
  if (route === 'pages') workspace = <PagesWorkspace mediaAssets={assets} navigationMenus={navigationMenus} />
  else if (route === 'media') workspace = <MediaLibrary assets={assets} loading={loadingMedia} onChange={setAssets} />
  else if (route === 'navigation') workspace = <NavigationWorkspace />
  else if (route === 'content-types') workspace = <ContentTypesWorkspace contentTypes={contentTypes} loading={loadingContentTypes} onChange={setContentTypes} />
  else if (route === 'taxonomies') workspace = <TaxonomyWorkspace taxonomies={taxonomies} onChange={setTaxonomies} />
  else if (route === 'entries') workspace = <EntriesWorkspace contentTypes={contentTypes} mediaAssets={assets} taxonomies={taxonomies} />
  else if (route === 'users') workspace = isOwner ? <UsersWorkspace currentUserId={currentUserId} /> : <Alert severity="error">{t('Owner access is required.')}</Alert>
  else if (route === 'extensions') workspace = isOwner ? <ExtensionsWorkspace /> : <Alert severity="error">{t('Owner access is required.')}</Alert>
  else if (route === 'settings') workspace = <SettingsWorkspace isOwner={isOwner} adminLocale={currentAdminLocale} onAdminLocaleChange={onAdminLocaleChange} onSessionEnded={onSessionEnded} />
  else workspace = <DashboardWorkspace onNavigate={onNavigate} />

  return <Stack spacing={3}>{error && <Alert severity="error">{error}</Alert>}{workspace}</Stack>
}
