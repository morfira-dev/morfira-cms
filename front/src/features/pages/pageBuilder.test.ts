import { describe, expect, it } from 'vitest'
import type { PageBlock } from './api'
import { copySectionBlocks, duplicatePageBlock, movePageBlock } from './pageBuilder'

const first: PageBlock = { id: '11111111-1111-4111-8111-111111111111', type: 'hero', data: { heading: 'One' } }
const second: PageBlock = { id: '22222222-2222-4222-8222-222222222222', type: 'richText', data: { content: '<p>Two</p>' } }

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
})
