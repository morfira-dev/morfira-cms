import { describe, expect, it } from 'vitest'
import {
  addNavigationItem,
  countNavigationItems,
  createNavigationItem,
  moveNavigationItem,
  removeNavigationItem,
  updateNavigationItem,
} from './tree'

describe('navigation tree operations', () => {
  it('adds, updates and removes a child without mutating the source tree', () => {
    const root = createNavigationItem('root')
    const child = createNavigationItem('child')
    const withChild = addNavigationItem([root], child, root.id)
    const updated = updateNavigationItem(withChild, child.id, 'label', 'Docs')
    const removed = removeNavigationItem(updated, child.id)

    expect(root.children).toEqual([])
    expect(updated[0]?.children[0]?.label).toBe('Docs')
    expect(countNavigationItems(updated)).toBe(2)
    expect(removed[0]?.children).toEqual([])
  })

  it('moves an item only among its siblings', () => {
    const first = createNavigationItem('first')
    const second = createNavigationItem('second')
    const nested = { ...createNavigationItem('nested'), children: [first, second] }

    const moved = moveNavigationItem([nested, createNavigationItem('other')], second.id, -1)

    expect(moved.map((item) => item.id)).toEqual(['nested', 'other'])
    expect(moved[0]?.children.map((item) => item.id)).toEqual(['second', 'first'])
  })

  it('does not create a fourth tree level', () => {
    const levelThree = createNavigationItem('level-three')
    const tree = [{
      ...createNavigationItem('root'),
      children: [{ ...createNavigationItem('level-two'), children: [levelThree] }],
    }]

    expect(addNavigationItem(tree, createNavigationItem('too-deep'), levelThree.id)).toEqual(tree)
  })
})
