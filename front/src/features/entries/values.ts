import type { ContentField } from '../contentTypes/api'
import type { EntryValue, EntryValues, PublishedEntry } from './api'

export function initializeEntryValues(fields: ContentField[], current: EntryValues = {}): EntryValues {
  return Object.fromEntries(fields.map((field) => [
    field.key,
    Object.prototype.hasOwnProperty.call(current, field.key)
      ? current[field.key]
      : (field.type === 'boolean' ? false : null),
  ]))
}

export function setEntryValue(values: EntryValues, key: string, value: EntryValue): EntryValues {
  return { ...values, [key]: value }
}

export function validateEntryValues(fields: ContentField[], values: EntryValues): string[] {
  const errors: string[] = []

  for (const field of fields) {
    const value = values[field.key]
    const empty = value === null || value === undefined || value === ''
    if (field.required && empty) {
      errors.push(`${field.label} is required.`)
      continue
    }
    if (empty) continue

    const valid = field.type === 'number'
      ? typeof value === 'number' && Number.isFinite(value)
      : field.type === 'boolean'
        ? typeof value === 'boolean'
        : typeof value === 'string'

    if (!valid) errors.push(`${field.label} has an invalid ${field.type} value.`)
  }

  return errors
}

export function publishedEntryUrl(entry: PublishedEntry): string {
  return `/api/v1/content/entries/${entry.contentType.apiName}/${entry.slug}`
}
