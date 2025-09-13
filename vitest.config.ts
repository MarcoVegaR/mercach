import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: [path.resolve(__dirname, 'resources/js/test/setup.ts')],
        include: ['resources/js/**/__tests__/**/*.{test,spec}.?(c|m)[jt]s?(x)'],
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
