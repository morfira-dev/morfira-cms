import type { NavigationItem } from './api'

export type NavigationItemField = 'label' | 'href' | 'openInNewTab'

export function createNavigationItem(id: string): NavigationItem {
  return { id, label: 'New item', href: '/', openInNewTab: false, children: [] }
}

export function countNavigationItems(items: NavigationItem[]): number {
  return items.reduce((total, item) => total + 1 + countNavigationItems(item.children), 0)
}

export function addNavigationItem(
  items: NavigationItem[],
  item: NavigationItem,
  parentId: string | null = null,
  depth = 0,
): NavigationItem[] {
  if (parentId === null) return [...items, item]

  return items.map((current) => {
    if (current.id === parentId) {
      return depth < 2 ? { ...current, children: [...current.children, item] } : current
    }

    return {
      ...current,
      children: addNavigationItem(current.children, item, parentId, depth + 1),
    }
  })
}

export function updateNavigationItem(
  items: NavigationItem[],
  id: string,
  field: NavigationItemField,
  value: string | boolean,
): NavigationItem[] {
  return items.map((item) => item.id === id
    ? { ...item, [field]: value }
    : { ...item, children: updateNavigationItem(item.children, id, field, value) })
}

export function removeNavigationItem(items: NavigationItem[], id: string): NavigationItem[] {
  return items
    .filter((item) => item.id !== id)
    .map((item) => ({ ...item, children: removeNavigationItem(item.children, id) }))
}

export function moveNavigationItem(items: NavigationItem[], id: string, offset: -1 | 1): NavigationItem[] {
  const index = items.findIndex((item) => item.id === id)

  if (index >= 0) {
    const destination = index + offset
    if (destination < 0 || destination >= items.length) return items

    const reordered = [...items]
    const [item] = reordered.splice(index, 1)
    reordered.splice(destination, 0, item)
    return reordered
  }

  return items.map((item) => ({
    ...item,
    children: moveNavigationItem(item.children, id, offset),
  }))
}
