import { defineConfig, devices } from '@playwright/test';

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
    // Reuse existing dev servers started manually (composer run dev)
    webServer: [
        {
            command: 'bash -lc "echo reuse artisan"',
            url: 'http://127.0.0.1:8000/login',
            reuseExistingServer: true,
            timeout: 60_000,
        },
        {
            command: 'bash -lc "echo reuse vite"',
            url: 'http://127.0.0.1:5176/@vite/client',
            reuseExistingServer: true,
            timeout: 60_000,
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
