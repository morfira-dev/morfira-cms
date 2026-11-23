import { describe, expect, it } from 'vitest'
import { countImportReviewIssues, filterImportItems, type ImportReviewFilter, type WordPressImportItem } from './filterImportItems'

const items = [
  { id: '10', title: 'Café Morfira', type: 'page', status: 'publish', state: 'planned', sourcePath: '/about', targetPath: '/cafe' },
  { id: '20', title: 'Из архива', type: 'post', status: 'publish', state: 'planned', sourcePath: '/archive', targetPath: '/posts/archived' },
  { id: '30', type: 'post', status: 'trash', state: 'unsupported_status', sourcePath: null, targetPath: null },
] as const satisfies readonly WordPressImportItem[]

const reviewedItems: WordPressImportItem[] = [
  { ...items[2], redirect: 'unsupported' },
  { ...items[0], images: { inline: 1, missingInline: 2, featured: 'missing' }, redirect: 'conflict' },
  { ...items[1], author: 'missing', redirect: 'planned' },
  { ...items[0], id: '40', images: { inline: 1, missingInline: 0, featured: 'linked' }, author: 'linked', redirect: 'registered' },
  { ...items[1], id: '50' },
]

describe('countImportReviewIssues', () => {
  it('counts materials with overlapping issues once in the combined total', () => {
    expect(countImportReviewIssues(reviewedItems)).toEqual({
      any: 3,
      inlineImages: 1,
      featuredImage: 1,
      author: 1,
      redirect: 2,
    })
  })

  it('reports zero counts for missing diagnostics and empty reports', () => {
    const zeroCounts = { any: 0, inlineImages: 0, featuredImage: 0, author: 0, redirect: 0 }
    expect(countImportReviewIssues(items)).toEqual(zeroCounts)
    expect(countImportReviewIssues([])).toEqual(zeroCounts)
  })
})

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

  it.each<[ImportReviewFilter, string[]]>([
    ['', ['30', '10', '20', '40', '50']],
    ['any', ['30', '10', '20']],
    ['inlineImages', ['10']],
    ['featuredImage', ['10']],
    ['author', ['20']],
    ['redirect', ['30', '10']],
  ])('keeps source order for the %s review filter', (issue, ids) => {
    const matches = filterImportItems(reviewedItems, { search: '', type: '', state: '', issue })
    expect(matches.map(({ id }) => id)).toEqual(ids)
  })

  it('intersects the review filter with search, type and status', () => {
    const filters = { search: '/ARCHIVE', type: 'post', state: 'planned', issue: 'author' } as const
    expect(filterImportItems(reviewedItems, filters)).toEqual([reviewedItems[2]])
    expect(filterImportItems(reviewedItems, { ...filters, search: 'other' })).toEqual([])
    expect(filterImportItems(reviewedItems, { ...filters, type: 'page' })).toEqual([])
    expect(filterImportItems(reviewedItems, { ...filters, state: 'unsupported_status' })).toEqual([])
  })
})
