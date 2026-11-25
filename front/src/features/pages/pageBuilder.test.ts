import { describe, expect, it } from 'vitest'
import type { PageBlock } from './api'
import type { ReusableSection } from './catalogApi'
import { copySectionBlocks, duplicatePageBlock, insertLinkedSection, movePageBlock, refreshLinkedSection, removePageBlock } from './pageBuilder'

const first: PageBlock = { id: '11111111-1111-4111-8111-111111111111', type: 'hero', data: { heading: 'One' } }
const second: PageBlock = { id: '22222222-2222-4222-8222-222222222222', type: 'richText', data: { content: '<p>Two</p>' } }
const section: ReusableSection = {
  id: '55555555-5555-4555-8555-555555555555',
  name: 'Contact section',
  version: 1,
  blocks: [first, second],
  createdAt: '2026-01-01T00:00:00Z',
  updatedAt: '2026-01-01T00:00:00Z',
}

describe('page builder transformations', () => {
  it('reorders blocks without changing their identifiers', () => {
    expect(movePageBlock([first, second], second.id, -1).map((block) => block.id)).toEqual([second.id, first.id])
  })

  it('duplicates and inserts independent blocks with fresh identifiers', () => {
    const ids = ['33333333-3333-4333-8333-333333333333', '44444444-4444-4444-8444-444444444444']
    expect(duplicatePageBlock([first], first.id, () => ids[0])).toHaveLength(2)
    const copies = copySectionBlocks([first, second], () => ids.shift()!)
    expect(copies.map((block) => block.id)).toEqual(['33333333-3333-4333-8333-333333333333', '44444444-4444-4444-8444-444444444444'])
    expect(copies[0]).not.toBe(first)
  })

  it('refreshes only the selected placement at its first member after block reordering', () => {
    let sequence = 0
    const inserted = insertLinkedSection({ blocks: [first, second], sectionLinks: [] }, section, () => `linked-${++sequence}`)
    const withTwoPlacements = insertLinkedSection(inserted, section, () => `linked-${++sequence}`)
    const reordered = {
      ...withTwoPlacements,
      blocks: [first, inserted.blocks[2], second, inserted.blocks[3], ...withTwoPlacements.blocks.slice(4)],
    }
    const [target, unaffected] = reordered.sectionLinks
    const latest = { ...section, version: 2, blocks: [second] }
    const refreshed = refreshLinkedSection(reordered, target, latest, () => 'refreshed')

    expect(refreshed.blocks.map((block) => block.id)).toEqual([first.id, 'refreshed', second.id, 'linked-3', 'linked-4'])
    expect(refreshed.blocks[0]).toBe(first)
    expect(refreshed.blocks[2]).toBe(second)
    expect(refreshed.sectionLinks).toEqual([{ sectionId: section.id, version: 2, blockIds: ['refreshed'] }, unaffected])
    expect(refreshed.sectionLinks[1]).toBe(unaffected)
    expect(refreshLinkedSection(refreshed, target, latest)).toBe(refreshed)
    expect(refreshLinkedSection(refreshed, refreshed.sectionLinks[0], section)).toBe(refreshed)
  })

  it('dissolves the link when one of its blocks is removed', () => {
    let sequence = 0
    const linked = insertLinkedSection({ blocks: [first], sectionLinks: [] }, section, () => `linked-${++sequence}`)
    const [removedId, remainingId] = linked.sectionLinks[0].blockIds
    const updated = removePageBlock(linked, removedId)

    expect(updated.blocks.map((block) => block.id)).toEqual([first.id, remainingId])
    expect(updated.sectionLinks).toEqual([])
  })
})
