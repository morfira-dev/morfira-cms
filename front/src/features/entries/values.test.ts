import { describe, expect, it } from 'vitest'
import type { ContentField } from '../contentTypes/api'
import { initializeEntryValues, publishedEntryUrl, setEntryValue, validateEntryValues } from './values'
import type { PublishedEntry } from './api'

const fields: ContentField[] = [
  { id: 'title', key: 'summary', label: 'Summary', type: 'text', required: true },
  { id: 'featured', key: 'featured', label: 'Featured', type: 'boolean', required: true },
  { id: 'score', key: 'score', label: 'Score', type: 'number', required: false },
]

describe('entry value helpers', () => {
  it('initializes schema keys and validates required values by type', () => {
    const initial = initializeEntryValues(fields)
    const withSummary = setEntryValue(initial, 'summary', 'Short text')

    expect(initial).toEqual({ summary: null, featured: false, score: null })
    expect(validateEntryValues(fields, withSummary)).toEqual([])
    expect(validateEntryValues(fields, { ...withSummary, score: 'wrong' })).toEqual([
      'Score has an invalid number value.',
    ])
  })

  it('uses the immutable published schema API name for the public URL', () => {
    const published = {
      snapshotId: '11111111-1111-4111-8111-111111111111',
      entryId: '22222222-2222-4222-8222-222222222222',
      sourceVersion: 2,
      contentType: {
        id: '33333333-3333-4333-8333-333333333333',
        name: 'Articles',
        apiName: 'legacy-articles',
        version: 1,
        fields: [],
      },
      title: 'Launch',
      slug: 'launch',
      values: {},
      publishedAt: '2026-10-12T10:00:00Z',
    } satisfies PublishedEntry

    expect(publishedEntryUrl(published)).toBe('/api/v1/content/entries/legacy-articles/launch')
  })
})
