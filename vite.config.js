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
