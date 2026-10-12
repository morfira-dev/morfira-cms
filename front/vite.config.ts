import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

export default defineConfig({
  base: '/admin/',
  plugins: [react()],
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
