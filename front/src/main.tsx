import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { App } from './App'
import { CmsThemeProvider } from './ui'

const root = document.getElementById('root')

if (!root) {
  throw new Error('Admin root element was not found.')
}

createRoot(root).render(
  <StrictMode>
    <CmsThemeProvider>
      <App />
    </CmsThemeProvider>
  </StrictMode>,
)
