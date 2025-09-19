import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import dotenv from 'dotenv';
// import path from 'path';
// dotenv.config({ path: path.resolve(__dirname, '.env') });

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: 'html',
    use: {
        baseURL: 'http://127.0.0.1:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'on-first-retry',
    },
    webServer: [
        {
            command: 'php artisan serve --host=127.0.0.1 --port=8000',
            // Use a public guest endpoint that returns 200 (avoids 302 redirects on root)
            url: 'http://127.0.0.1:8000/login',
            reuseExistingServer: !process.env.CI,
            timeout: 300_000,
        },
        {
            command: 'vite --host 127.0.0.1 --port 5176 --strictPort',
            // Use a Vite endpoint that is guaranteed to return 200 once ready
            url: 'http://127.0.0.1:5176/@vite/client',
            reuseExistingServer: !process.env.CI,
            timeout: 300_000,
        },
    ],
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.ts/ },
        {
            name: 'chromium-admin',
            use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/state.admin.json' },
            dependencies: ['setup'],
        },
        {
            name: 'chromium-viewer',
            use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/state.viewer.json' },
            dependencies: ['setup'],
        },
        {
            name: 'firefox-admin',
            use: { ...devices['Desktop Firefox'], storageState: 'tests/e2e/state.admin.json' },
            dependencies: ['setup'],
        },
        {
            name: 'firefox-viewer',
            use: { ...devices['Desktop Firefox'], storageState: 'tests/e2e/state.viewer.json' },
            dependencies: ['setup'],
        },
    ],
});
