import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        cors: true,
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            'ag-grid-community/styles': path.resolve(__dirname, 'node_modules/ag-grid-community/styles'),
            mermaid: path.resolve(__dirname, 'node_modules/mermaid/dist/mermaid.core.mjs'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('ag-grid-community') || id.includes('ag-grid-vue3')) {
                        return 'vendor-ag-grid'
                    }
                    if (id.includes('grapesjs')) {
                        return 'vendor-grapesjs'
                    }
                    if (id.includes('mermaid')) {
                        return 'vendor-mermaid'
                    }
                    if (id.includes('@tiptap/')) {
                        return 'vendor-tiptap'
                    }
                    if (id.includes('@vue-flow/')) {
                        return 'vendor-vue-flow'
                    }
                    if (id.includes('@inertiajs') || id.includes('/vue/')) {
                        return 'vendor-core'
                    }
                    if (id.includes('/resources/js/Pages/')) {
                        const nested = id.match(/\/Pages\/([^/]+)\//)
                        if (nested) {
                            return `page-${nested[1].toLowerCase()}`
                        }
                        const flat = id.match(/\/Pages\/([^/.]+)\.vue/)
                        if (flat) {
                            return `page-${flat[1].toLowerCase()}`
                        }
                    }
                    if (id.includes('node_modules')) {
                        return 'vendor-common'
                    }
                },
                chunkFileNames: 'assets/[name]-[hash].js',
                entryFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]',
            },
        },
        chunkSizeWarningLimit: 1000,
    },
    optimizeDeps: {
        include: [
            'vue',
            '@inertiajs/vue3',
            'ag-grid-community',
            'ag-grid-vue3',
            'ag-grid-community/styles/ag-grid.css',
            'grapesjs',
            'grapesjs-preset-newsletter',
            'mermaid',
        ],
    },
})
