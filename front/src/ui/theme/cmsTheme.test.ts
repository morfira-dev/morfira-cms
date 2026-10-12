import { describe, expect, it } from 'vitest'
import { cmsTheme } from './cmsTheme'

describe('cmsTheme', () => {
  it('uses the CMS brand color and accessible system font fallback', () => {
    expect(cmsTheme.palette.primary.main).toBe('#2563eb')
    expect(cmsTheme.typography.fontFamily).toContain('system-ui')
  })
})
