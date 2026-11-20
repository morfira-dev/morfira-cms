import type { MessageKey } from '../../i18n/messages'

export const IMPORT_REPORT_PAGE_SIZE = 25

export const importPaginationLabels = {
  first: 'First report page',
  last: 'Last report page',
  next: 'Next report page',
  previous: 'Previous report page',
} as const

const statusKeys: ReadonlyMap<string, MessageKey> = new Map([
  ['planned', 'Ready to import'],
  ['imported', 'Imported as draft'],
  ['already_imported', 'Already imported'],
  ['unsupported_status', 'Unsupported source status'],
  ['invalid', 'Invalid content'],
  ['conflict', 'Import conflict'],
  ['existing', 'Already exists'],
  ['unavailable', 'Unavailable for reuse'],
  ['missing_term', 'Missing taxonomy term'],
  ['missing_declaration', 'Missing author declaration'],
  ['media_directory_required', 'Media files required'],
])

export function importStatusKey(state: string): MessageKey | undefined {
  return statusKeys.get(state)
}

const attachmentReasonKeys = [
  'The WordPress attachment host is not allowed.',
  'WordPress attachments must use HTTPS on the default port.',
  'The WordPress attachment has an invalid file path.',
  'The WordPress attachment exceeds the image size limit.',
  'The WordPress attachment is empty.',
  'Default alternative text must not exceed 500 characters.',
  'No reusable media asset mapping is available.',
] as const satisfies readonly MessageKey[]

export function attachmentReasonKey(reason: string): MessageKey | undefined {
  return attachmentReasonKeys.find((key) => key === reason)
}
