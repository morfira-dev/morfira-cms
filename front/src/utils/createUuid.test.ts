import { afterEach, describe, expect, it, vi } from 'vitest'
import { createUuid } from './createUuid'

afterEach(() => vi.unstubAllGlobals())

describe('createUuid', () => {
  it('creates a UUID v4 when randomUUID is unavailable outside a secure context', () => {
    vi.stubGlobal('crypto', {
      getRandomValues: (value: Uint8Array) => {
        value.set(Array.from({ length: 16 }, (_, index) => index))
        return value
      },
    })

    expect(createUuid()).toBe('00010203-0405-4607-8809-0a0b0c0d0e0f')
  })
})
