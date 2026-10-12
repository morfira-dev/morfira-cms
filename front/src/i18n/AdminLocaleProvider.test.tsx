import { renderToStaticMarkup } from 'react-dom/server'
import { describe, expect, it } from 'vitest'
import { AdminLocaleProvider, useAdminLocale } from './AdminLocaleProvider'
import { russianMessages } from './messages'

function Probe() {
  const { locale, t } = useAdminLocale()
  return <span>{locale}:{t('Pages')}</span>
}

describe('AdminLocaleProvider', () => {
  it('defaults to English independently from site settings', () => {
    expect(renderToStaticMarkup(<AdminLocaleProvider><Probe /></AdminLocaleProvider>)).toContain('en:Pages')
  })

  it('keeps every Russian message non-empty and preserves interpolation parameters', () => {
    for (const [source, translation] of Object.entries(russianMessages)) {
      expect(translation.trim(), source).not.toBe('')
      expect(placeholders(translation), source).toEqual(placeholders(source))
    }
  })
})

function placeholders(message: string): string[] {
  return [...message.matchAll(/{{([^}]+)}}/g)].map((match) => match[1]).sort()
}
