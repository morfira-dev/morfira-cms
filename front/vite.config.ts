import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

const adminBase = '/admin/'
const adminRoot = adminBase.slice(0, -1)

export default defineConfig({
  base: adminBase,
  plugins: [
    {
      name: 'admin-base-redirect',
      configureServer({ middlewares }) {
        middlewares.use((request, response, next) => {
          const url = 'url' in request && typeof request.url === 'string' ? request.url : ''
          if (url.split('?')[0] !== adminRoot) {
            next()
            return
          }

          response.writeHead(308, { Location: adminBase + url.slice(adminRoot.length) })
          response.end()
        })
      },
    },
    react(),
  ],
  test: {
    exclude: ['e2e/**', 'node_modules/**', 'dist/**'],
  },
  server: {
    proxy: {
      '/health': {
        target: 'http://app:8080',
        changeOrigin: true,
      },
      '/api': {
        target: 'http://app:8080',
        changeOrigin: true,
      },
      '/_preview': {
        target: 'http://app:8080',
        changeOrigin: true,
      },
      '/media': {
        target: 'http://app:8080',
        changeOrigin: true,
      },
    },
  },
})
