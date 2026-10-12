import { getContentTypes } from '../contentTypes/api'
import { getMedia } from '../media/api'
import { getPages } from '../pages/api'
import { getTaxonomies } from '../taxonomy/api'

export type DashboardCounts = { pages: number; media: number; contentTypes: number; taxonomies: number }

export async function getDashboardCounts(signal?: AbortSignal): Promise<DashboardCounts> {
  const [pages, media, contentTypes, taxonomies] = await Promise.all([
    getPages(signal),
    getMedia(signal),
    getContentTypes(signal),
    getTaxonomies(signal),
  ])
  return { pages: pages.length, media: media.length, contentTypes: contentTypes.length, taxonomies: taxonomies.length }
}
