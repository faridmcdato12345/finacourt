import { defineConfig } from 'vite';
import path from 'node:path';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(process.cwd(), 'resources/js'),
        },
    },
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/pwa.js', 'resources/js/public-selects.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
