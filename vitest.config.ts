import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// Separate from vite.config.ts: the tests need React and jsdom, not the Laravel plugin.
export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    setupFiles: ['resources/js/test/setup.ts'],
    include: ['resources/js/**/*.test.{ts,tsx}'],
  },
})
