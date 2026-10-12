import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { App } from './App'
import { CmsThemeProvider } from './ui'
import { AdminLocaleProvider } from './i18n/AdminLocaleProvider'

const root = document.getElementById('root')

if (!root) {
  throw new Error('Admin root element was not found.')
}

createRoot(root).render(
  <StrictMode>
    <AdminLocaleProvider>
      <CmsThemeProvider>
        <App />
      </CmsThemeProvider>
    </AdminLocaleProvider>
  </StrictMode>,
)
