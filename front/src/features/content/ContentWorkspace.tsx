import { useEffect, useRef, useState } from 'react'
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
import { WordPressImportWorkspace } from '../wordpressImport/WordPressImportWorkspace'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import type { AdminLocale } from '../../i18n/AdminLocaleProvider'
import { scheduleAbortableLoad } from '../../utils/scheduleAbortableLoad'

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
  const { errorMessage, t } = useAdminLocale()
  const localizeError = useRef(errorMessage)
  const [assets, setAssets] = useState<MediaAsset[]>([])
  const [loadingMedia, setLoadingMedia] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [contentTypes, setContentTypes] = useState<ContentType[]>([])
  const [loadingContentTypes, setLoadingContentTypes] = useState(true)
  const [taxonomies, setTaxonomies] = useState<Taxonomy[]>([])
  const [navigationMenus, setNavigationMenus] = useState<NavigationMenu[]>([])

  useEffect(() => { localizeError.current = errorMessage }, [errorMessage])

  useEffect(() => {
    if (!['pages', 'media', 'entries'].includes(route)) return
    return scheduleAbortableLoad((signal) => {
      setLoadingMedia(true)
      getMedia(signal)
        .then((items) => { if (!signal.aborted) setAssets(items) })
        .catch((reason: unknown) => {
          if (!signal.aborted) setError(localizeError.current(reason, 'Could not load the media library.'))
        })
        .finally(() => { if (!signal.aborted) setLoadingMedia(false) })
    })
  }, [route])

  useEffect(() => {
    if (route !== 'pages') return
    return scheduleAbortableLoad((signal) => {
      getNavigationMenus(signal)
        .then((items) => { if (!signal.aborted) setNavigationMenus(items) })
        .catch((reason: unknown) => {
          if (!signal.aborted) setError(localizeError.current(reason, 'Could not load navigation choices.'))
        })
    })
  }, [route])

  useEffect(() => {
    if (!['taxonomies', 'entries'].includes(route)) return
    return scheduleAbortableLoad((signal) => {
      getTaxonomies(signal)
        .then((items) => { if (!signal.aborted) setTaxonomies(items) })
        .catch((reason: unknown) => {
          if (!signal.aborted) setError(localizeError.current(reason, 'Could not load taxonomies.'))
        })
    })
  }, [route])

  useEffect(() => {
    if (!['content-types', 'entries'].includes(route)) return
    return scheduleAbortableLoad((signal) => {
      setLoadingContentTypes(true)
      getContentTypes(signal)
        .then((items) => { if (!signal.aborted) setContentTypes(items) })
        .catch((reason: unknown) => {
          if (!signal.aborted) setError(localizeError.current(reason, 'Could not load content types.'))
        })
        .finally(() => { if (!signal.aborted) setLoadingContentTypes(false) })
    })
  }, [route])

  let workspace
  if (route === 'pages') workspace = <PagesWorkspace isOwner={isOwner} mediaAssets={assets} navigationMenus={navigationMenus} />
  else if (route === 'media') workspace = <MediaLibrary assets={assets} loading={loadingMedia} onChange={setAssets} />
  else if (route === 'navigation') workspace = <NavigationWorkspace />
  else if (route === 'content-types') workspace = <ContentTypesWorkspace contentTypes={contentTypes} loading={loadingContentTypes} onChange={setContentTypes} />
  else if (route === 'taxonomies') workspace = <TaxonomyWorkspace taxonomies={taxonomies} onChange={setTaxonomies} />
  else if (route === 'entries') workspace = <EntriesWorkspace contentTypes={contentTypes} loadingContentTypes={loadingContentTypes} mediaAssets={assets} taxonomies={taxonomies} />
  else if (route === 'users') workspace = isOwner ? <UsersWorkspace currentUserId={currentUserId} /> : <Alert severity="error">{t('Owner access is required.')}</Alert>
  else if (route === 'extensions') workspace = isOwner ? <ExtensionsWorkspace /> : <Alert severity="error">{t('Owner access is required.')}</Alert>
  else if (route === 'import') workspace = isOwner ? <WordPressImportWorkspace /> : <Alert severity="error">{t('Owner access is required.')}</Alert>
  else if (route === 'settings') workspace = <SettingsWorkspace isOwner={isOwner} adminLocale={currentAdminLocale} onAdminLocaleChange={onAdminLocaleChange} onSessionEnded={onSessionEnded} />
  else workspace = <DashboardWorkspace onNavigate={onNavigate} />

  return <Stack spacing={3}>{error && <Alert severity="error">{error}</Alert>}{workspace}</Stack>
}
