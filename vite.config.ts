import { resolve } from 'path'
import { defineConfig } from 'vite'
import dts from 'vite-plugin-dts'

export default defineConfig({
    build: {
        outDir: 'dist',
        lib: {
            entry: resolve(__dirname, 'src/playwright/src/index.ts'),
            name: 'LaravelPlaywright',
            fileName: 'laravel-playwright',
        },
        rollupOptions: {
            external: ['@playwright/test'],
            output: {
                globals: {
                    '@playwright/test': 'PlaywrightTest',
                },
            },
        },
    },
    plugins: [dts({ include: ['src/playwright/src'] })],
})
