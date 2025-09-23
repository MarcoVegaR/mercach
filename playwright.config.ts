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
const isCI = !!process.env.CI;

// Start PHP server always. Start Vite dev server only locally; in CI we'll build assets instead.
const envVars: Record<string, string> = {
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    // IMPORTANT: Use a persistent session driver for E2E (array resets per request -> CSRF 419)
    SESSION_DRIVER: 'file',
    // Normalize app URL and cookie domain for consistent cookies in tests
    APP_URL: 'http://127.0.0.1:8000',
    SESSION_DOMAIN: '127.0.0.1',
};
let prepDbCmd = '';
if (isCI) {
    envVars.DB_CONNECTION = 'sqlite';
    envVars.DB_DATABASE = 'database/database.sqlite';
    prepDbCmd = 'rm -f database/database.sqlite && touch database/database.sqlite; ';
} else {
    envVars.DB_CONNECTION = 'pgsql';
    envVars.DB_HOST = '127.0.0.1';
    envVars.DB_PORT = '5434';
    envVars.DB_DATABASE = 'mercach_test';
    envVars.DB_USERNAME = 'postgres';
    envVars.DB_PASSWORD = 'postgres';
}

const webServers = [
    {
        command:
            'bash -lc "php artisan config:clear && php artisan cache:clear || true; ' +
            prepDbCmd +
            'php artisan migrate:fresh --seed --force --env=testing; ' +
            'php -S 127.0.0.1:8000 -t public server.php"',
        // Use login endpoint to ensure full app is ready (same as attached config)
        url: 'http://127.0.0.1:8000/login',
        reuseExistingServer: !isCI,
        timeout: 600_000,
        // Force testing environment + test DB to prevent touching production data
        env: envVars,
    },
    // Vite dev server only for local runs
    // In CI we rely on built assets (npm run build) so we don't need Vite dev server
    ...(!isCI
        ? ([
              {
                  command: 'vite --host 127.0.0.1 --port 5176 --strictPort',
                  // Use a Vite endpoint that is guaranteed to return 200 once ready
                  url: 'http://127.0.0.1:5176/@vite/client',
                  reuseExistingServer: !isCI,
                  timeout: 600_000,
              },
          ] as const)
        : ([] as const)),
];

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
    webServer: webServers,
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
