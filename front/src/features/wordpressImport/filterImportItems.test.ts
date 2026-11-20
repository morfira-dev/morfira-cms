import { describe, expect, it } from 'vitest'
import { filterImportItems, type WordPressImportItem } from './filterImportItems'

const items: WordPressImportItem[] = [
  { id: '10', title: 'Café Morfira', type: 'page', status: 'publish', state: 'planned', sourcePath: '/about', targetPath: '/cafe' },
  { id: '20', title: 'Из архива', type: 'post', status: 'publish', state: 'planned', sourcePath: '/archive', targetPath: '/posts/archived' },
  { id: '30', type: 'post', status: 'trash', state: 'unsupported_status', sourcePath: null, targetPath: null },
]

describe('filterImportItems', () => {
  it('preserves source order with empty filters', () => {
    expect(filterImportItems(items, { search: '  ', type: '', state: '' })).toEqual(items)
  })

  it.each([
    ['  CAFÉ  ', '10'],
    ['ИЗ АРХИВА', '20'],
    ['/ABOUT', '10'],
    ['/POSTS/archived', '20'],
    ['30', '30'],
  ])('matches the title, paths or ID for %s', (search, id) => {
    expect(filterImportItems(items, { search, type: '', state: '' }).map((item) => item.id)).toEqual([id])
  })

  it('applies search, type and status together', () => {
    expect(filterImportItems(items, { search: '/', type: 'post', state: 'planned' })).toEqual([items[1]])
    expect(filterImportItems(items, { search: '/', type: 'post', state: 'unsupported_status' })).toEqual([])
  })
})
