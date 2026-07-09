import {defineConfig} from 'vite'
import postcss from '@vituum/vite-plugin-postcss'
import { viteStaticCopy } from 'vite-plugin-static-copy'
import path from 'path'
import fs from 'node:fs'

const assetsDir = path.resolve(__dirname, '../assets')
const sourcesDir = path.resolve(__dirname, './src')

/** Рекурсивно собирает пути файлов с заданным расширением. */
const collectFiles = (dir, ext) => {
    const out = []
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name)
        if (entry.isDirectory()) out.push(...collectFiles(full, ext))
        else if (entry.name.endsWith(ext)) out.push(full)
    }
    return out
}

/**
 * Twig не входит в граф модулей Rollup, поэтому в режиме `vite build --watch`
 * его изменения сами по себе не отслеживаются (полное копирование делает
 * viteStaticCopy только при старте сборки). Этот плагин в watch-режиме
 * регистрирует twig-файлы в watcher'е Rollup и докопирует изменённый файл
 * в outDir с сохранением структуры путей.
 */
const twigWatch = ({ watchDir, baseDir, outDir }) => ({
    name: 'twig-watch',
    buildStart() {
        if (!this.meta.watchMode) return
        for (const file of collectFiles(watchDir, '.twig')) this.addWatchFile(file)
        this.addWatchFile(watchDir) // чтобы реагировать на добавление новых файлов
    },
    watchChange(id) {
        if (!id.endsWith('.twig') || !fs.existsSync(id)) return
        const target = path.join(outDir, path.relative(baseDir, id))
        fs.mkdirSync(path.dirname(target), { recursive: true })
        fs.copyFileSync(id, target)
    },
})

export default defineConfig({
    base: './',
    resolve: {
        alias: {
            '@images'   : `${sourcesDir}/images/`,
            '@fonts'    : `${sourcesDir}/fonts/`,
            '@styles'   : `${sourcesDir}/styles/`,
            '@scripts'  : `${sourcesDir}/scripts/`,
        }
    },
    plugins: [
        twigWatch({ watchDir: `${sourcesDir}/twig`, baseDir: sourcesDir, outDir: assetsDir }),
        viteStaticCopy({
            targets: [
                {
                    src: `${sourcesDir}/twig/**/*.twig`,
                    dest: '',
                    rename: { stripBase: 1 }
                },
                {
                    src: `${sourcesDir}/images/**/*.*`,
                    dest: '',
                    rename: { stripBase: 1 }
                }
            ]
        }),
        postcss({
            autoprefixer: {
                overrideBrowserslist: ['last 2 versions', '>1%']
            }
        }),
    ],
    build: {
        outDir: assetsDir,
        rollupOptions: {
            input: {
                main: `${sourcesDir}/scripts/main.js`,
            },
            output: {
                // Настройка для JS файлов (точки входа)
                entryFileNames: `js/[name].js`,
                // Настройка для чанков (разделенных файлов)
                chunkFileNames: `js/[name].js`,
                // Настройка для CSS, изображений и шрифтов
                assetFileNames: (assetInfo) => {
                    const ext = assetInfo.name?.split('.').pop()

                    if (/css/.test(ext))                            return 'css/[name][extname]'
                    if (/png|jpe?g|svg|gif|webp|ico/.test(ext))     return 'images/[name][extname]'
                    if (/woff2?|ttf|eot|otf/.test(ext))             return 'fonts/[name][extname]'

                    // всё остальное в assets
                    return 'assets/[name][extname]'
                },
            },
        },
    },
})
