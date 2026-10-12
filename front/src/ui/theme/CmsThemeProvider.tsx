import { CssBaseline } from '@mui/material'
import { ThemeProvider } from '@mui/material/styles'
import type { PropsWithChildren } from 'react'
import { cmsTheme } from './cmsTheme'

export function CmsThemeProvider({ children }: PropsWithChildren) {
  return (
    <ThemeProvider theme={cmsTheme}>
      <CssBaseline />
      {children}
    </ThemeProvider>
  )
}
