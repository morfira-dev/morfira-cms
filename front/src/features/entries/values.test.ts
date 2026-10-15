import { describe, expect, it } from 'vitest'
import type { ContentField } from '../contentTypes/api'
import { hasVisibleRichText, initializeEntryValues, publishedEntryUrl, setEntryValue, validateEntryValues } from './values'
import type { PublishedEntry } from './api'

const fields: ContentField[] = [
  { id: 'title', key: 'summary', label: 'Summary', type: 'text', required: true, multiple: false },
  { id: 'featured', key: 'featured', label: 'Featured', type: 'boolean', required: true, multiple: false },
  { id: 'score', key: 'score', label: 'Score', type: 'number', required: false, multiple: false },
  { id: 'body', key: 'body', label: 'Body', type: 'richText', required: true, multiple: false },
  { id: 'related', key: 'related', label: 'Related', type: 'relation', required: true, targetContentTypeId: 'target', multiple: true },
]

describe('entry value helpers', () => {
  it('initializes schema keys and validates required values by type', () => {
    const initial = initializeEntryValues(fields)
    const withSummary = setEntryValue(setEntryValue(initial, 'summary', 'Short text'), 'body', '<p>Useful content</p>')

    expect(initial).toEqual({ summary: null, featured: false, score: null, body: null, related: [] })
    const complete = setEntryValue(withSummary, 'related', ['first', 'second'])
    expect(validateEntryValues(fields, complete)).toEqual([])
    expect(validateEntryValues(fields, { ...complete, score: 'wrong' })).toEqual([
      'Score has an invalid number value.',
    ])
  })

  it('treats formatting-only rich text as empty', () => {
    expect(hasVisibleRichText('<p><br></p>')).toBe(false)
    expect(hasVisibleRichText('<p>&nbsp;&#160;&#xA0;</p>')).toBe(false)
    expect(hasVisibleRichText('<script>alert(1)</script>')).toBe(false)
    expect(hasVisibleRichText('<p><strong>Useful content</strong></p>')).toBe(true)
    expect(validateEntryValues(fields, {
      summary: 'Short text',
      featured: false,
      score: null,
      body: '<p><br></p>',
      related: ['first'],
    })).toEqual(['Body is required.'])
  })

  it('validates required unique multiple relations', () => {
    const values = { summary: 'Short text', featured: false, score: null, body: '<p>Body</p>', related: [] }
    expect(validateEntryValues(fields, values)).toEqual(['Related is required.'])
    expect(validateEntryValues(fields, { ...values, related: ['same', 'same'] })).toEqual(['Related has an invalid relation value.'])
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
