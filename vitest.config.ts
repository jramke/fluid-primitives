import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        environmentOptions: { jsdom: { url: 'https://example.com/' } },
        include: ['tests/Client/**/*.test.ts'],
    },
});
