import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        lib: {
            entry: path.resolve(__dirname, 'resources/js/pages/Translations/Index.vue'),
            name: 'TranslationsModule',
            fileName: 'translations-module',
            formats: ['es'],
        },
        outDir: path.resolve(__dirname, 'dist'),
        rollupOptions: {
            external: ['vue', 'notivue'],
            output: {
                globals: {
                    vue: 'Vue',
                    notivue: 'Notivue',
                },
            },
        },
    },
});