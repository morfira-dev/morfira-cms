import { CssBaseline } from '@mui/material'
import { enUS, ruRU } from '@mui/material/locale'
import { createTheme, ThemeProvider } from '@mui/material/styles'
import { type PropsWithChildren, useMemo } from 'react'
import { useAdminLocale } from '../../i18n/AdminLocaleProvider'
import { cmsTheme } from './cmsTheme'

export function CmsThemeProvider({ children }: PropsWithChildren) {
  const { locale } = useAdminLocale()
  const localizedTheme = useMemo(() => createTheme(cmsTheme, locale === 'ru' ? ruRU : enUS), [locale])

  return (
    <ThemeProvider theme={localizedTheme}>
      <CssBaseline />
      {children}
    </ThemeProvider>
  )
}
