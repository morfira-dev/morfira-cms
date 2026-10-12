import { createUuid } from '../../utils/createUuid'
import type { PageBlock } from './api'

export function movePageBlock(blocks: PageBlock[], id: string, offset: -1 | 1): PageBlock[] {
  const from = blocks.findIndex((block) => block.id === id)
  const to = from + offset
  if (from < 0 || to < 0 || to >= blocks.length) return blocks
  const reordered = [...blocks]
  const [block] = reordered.splice(from, 1)
  reordered.splice(to, 0, block)
  return reordered
}

export function duplicatePageBlock(blocks: PageBlock[], id: string, uuid = createUuid): PageBlock[] {
  const index = blocks.findIndex((block) => block.id === id)
  if (index < 0) return blocks
  const clone = { ...structuredClone(blocks[index]), id: uuid() }
  const duplicated = [...blocks]
  duplicated.splice(index + 1, 0, clone)
  return duplicated
}

export function copySectionBlocks(blocks: PageBlock[], uuid = createUuid): PageBlock[] {
  return blocks.map((block) => ({ ...structuredClone(block), id: uuid() }))
}
