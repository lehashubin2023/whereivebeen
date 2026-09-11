import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    preload: [{ weight: 400 }],
                }),
                bunny('Cinzel', {
                    weights: [400, 500, 600, 700],
                    preload: [{ weight: 600 }],
                }),
                bunny('Cinzel Decorative', {
                    weights: [400, 700, 900],
                    preload: [{ weight: 900 }],
                }),
                bunny('Marcellus', {
                    weights: [400],
                    preload: false,
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
