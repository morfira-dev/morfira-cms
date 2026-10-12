import type { ContentField, ContentFieldType } from './api'

export function createContentField(id: string): ContentField {
  return { id, key: 'newField', label: 'New field', type: 'text', required: false }
}

export function updateContentField(
  fields: ContentField[],
  id: string,
  changes: Partial<Pick<ContentField, 'key' | 'label' | 'type' | 'required'>>,
): ContentField[] {
  return fields.map((field) => field.id === id ? { ...field, ...changes } : field)
}

export function removeContentField(fields: ContentField[], id: string): ContentField[] {
  return fields.filter((field) => field.id !== id)
}

export function moveContentField(fields: ContentField[], id: string, offset: -1 | 1): ContentField[] {
  const index = fields.findIndex((field) => field.id === id)
  const destination = index + offset
  if (index < 0 || destination < 0 || destination >= fields.length) return fields

  const moved = [...fields]
  const [field] = moved.splice(index, 1)
  moved.splice(destination, 0, field)
  return moved
}

export const contentFieldTypes: ContentFieldType[] = ['text', 'textarea', 'number', 'boolean', 'date', 'media']
