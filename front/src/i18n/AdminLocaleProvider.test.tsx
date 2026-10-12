import { renderToStaticMarkup } from 'react-dom/server'
import { describe, expect, it } from 'vitest'
import { AdminLocaleProvider, useAdminLocale } from './AdminLocaleProvider'

function Probe() {
  const { locale, t } = useAdminLocale()
  return <span>{locale}:{t('Pages')}</span>
}

describe('AdminLocaleProvider', () => {
  it('defaults to English independently from site settings', () => {
    expect(renderToStaticMarkup(<AdminLocaleProvider><Probe /></AdminLocaleProvider>)).toContain('en:Pages')
  })
})
