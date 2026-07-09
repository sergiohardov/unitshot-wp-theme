import {defineConfig} from 'vite'
import react from '@vitejs/plugin-react'
import {resolve} from 'node:path'
import tailwindcss from '@tailwindcss/vite'

/**
 * Сборка React-островов админки. Один entry = одна страница.
 * Каждая страница даёт стабильные dist/<page>.js и dist/<page>.css
 * (общий вендор - в dist/chunks/*, подтягивается импортом автоматически).
 * HTML не генерим - контейнер монтирования выводит PHP.
 */
const assetsDir = resolve(__dirname, '../assets')
const sourcesDir = resolve(__dirname, './src')

export default defineConfig({
    base: './',
    resolve: {
        alias: {
            '@components': `${sourcesDir}/components/`,
        }
    },
    plugins: [react(), tailwindcss()],
    build: {
        outDir: assetsDir,
        emptyOutDir: true,
        manifest: false,
        rollupOptions: {
            input: {
                'product-attributes': `${sourcesDir}/pages/product-attributes.jsx`,
                'product-metabox': `${sourcesDir}/pages/product-metabox.jsx`,
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (info) =>
                    info.name && info.name.endsWith('.css')
                        // ? '[name][extname]' // dist/<page>.css — стабильное имя для enqueue
                        ? 'admin.css' // общий CSS всех страниц (Tailwind) — стабильное имя для enqueue
                        : 'assets/[name]-[hash][extname]',
            },
        },
    },
})
