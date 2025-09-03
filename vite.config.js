import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
    build: {
        sourcemap: mode === 'development' ? 'inline' : false,
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor': [
                        'vue',
                        'axios',
                        '@fortawesome/fontawesome-svg-core',
                        '@tiptap/extension-underline',
                        '@tiptap/extension-link',
                        '@tiptap/core',
                        'prosemirror-state',
                        '@tiptap/extension-table',
                        '@tiptap/extension-text-style'
                    ]
                }
            }
        }
    },
    server: {
        host: 'localhost', // Avoid [::1] IPv6 host to prevent mixed-host CORS issues
        port: 5173,
        strictPort: true,
        cors: true,
        hmr: {
            host: 'localhost',
            protocol: 'ws',
            port: 5173,
        },
        origin: 'http://localhost:5173',
    },
    optimizeDeps: {
        include: [
            'vue',
            'axios',
            '@fortawesome/fontawesome-svg-core',
            '@tiptap/extension-underline',
            '@tiptap/extension-link',
            '@tiptap/core',
            'prosemirror-state',
            '@tiptap/extension-table',
            '@tiptap/extension-text-style'
        ]
    }
}));
