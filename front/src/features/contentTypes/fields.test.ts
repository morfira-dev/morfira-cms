import { describe, expect, it } from 'vitest'
import { contentFieldTypes, createContentField, moveContentField, removeContentField, updateContentField } from './fields'

describe('content field operations', () => {
  it('updates, reorders and removes fields immutably', () => {
    const first = createContentField('first')
    const second = createContentField('second')
    const source = [first, second]
    const updated = updateContentField(source, second.id, { key: 'summary', type: 'textarea', required: true })
    const moved = moveContentField(updated, second.id, -1)

    expect(source[1]?.key).toBe('newField')
    expect(moved.map((field) => field.id)).toEqual(['second', 'first'])
    expect(moved[0]).toMatchObject({ key: 'summary', type: 'textarea', required: true })
    expect(removeContentField(moved, second.id)).toEqual([first])
  })

  it('offers rich text as a schema field type', () => {
    expect(contentFieldTypes).toContain('richText')
  })

  it('initializes fields as single-valued and can enable multiple relations', () => {
    const field = createContentField('relation')
    expect(field.multiple).toBe(false)
    expect(updateContentField([field], field.id, { type: 'relation', multiple: true })[0]).toMatchObject({ type: 'relation', multiple: true })
  })
})
