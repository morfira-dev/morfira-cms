export const MAX_SOURCE_KEY_LENGTH = 80

export function isWordPressSourceKey(value: string): boolean {
  return value.length <= MAX_SOURCE_KEY_LENGTH && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value)
}
