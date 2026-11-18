import vue from '@vitejs/plugin-vue';
import path from 'path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './assets/js'),
        },
    },
    test: {
        include: ['assets/js/**/*.test.ts'],
    },
});
