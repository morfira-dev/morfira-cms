import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
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
