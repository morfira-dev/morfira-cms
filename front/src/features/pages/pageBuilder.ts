import { createUuid } from '../../utils/createUuid'
import type { PageBlock, PageSectionLink } from './api'
import type { ReusableSection } from './catalogApi'

export interface PageComposition {
  blocks: PageBlock[]
  sectionLinks: PageSectionLink[]
}

export function richTextPreview(content: string): string {
  return content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

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

export function insertLinkedSection(page: PageComposition, section: ReusableSection, uuid = createUuid): PageComposition {
  const copies = copySectionBlocks(section.blocks, uuid)
  return {
    blocks: [...page.blocks, ...copies],
    sectionLinks: [...page.sectionLinks, { sectionId: section.id, version: section.version, blockIds: copies.map((block) => block.id) }],
  }
}

export function refreshLinkedSection(page: PageComposition, link: PageSectionLink, section: ReusableSection, uuid = createUuid): PageComposition {
  const memberIds = new Set(link.blockIds)
  const firstMember = page.blocks.findIndex((block) => memberIds.has(block.id))
  if (firstMember < 0 || section.id !== link.sectionId || section.version <= link.version || !page.sectionLinks.includes(link)) return page

  const copies = copySectionBlocks(section.blocks, uuid)
  const blocks = [...page.blocks.slice(0, firstMember), ...copies, ...page.blocks.slice(firstMember).filter((block) => !memberIds.has(block.id))]
  const sectionLinks = page.sectionLinks.map((placement) => placement === link
    ? { sectionId: section.id, version: section.version, blockIds: copies.map((block) => block.id) }
    : placement)

  return { blocks, sectionLinks }
}

export function removePageBlock(page: PageComposition, id: string): PageComposition {
  return {
    blocks: page.blocks.filter((block) => block.id !== id),
    sectionLinks: page.sectionLinks.filter((link) => !link.blockIds.includes(id)),
  }
}
