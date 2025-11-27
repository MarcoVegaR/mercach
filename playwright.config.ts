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

// Environment-specific configuration for E2E tests
// Both CI and Local use PostgreSQL (same as production) to avoid SQL compatibility issues
const envVars: Record<string, string> = {
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    // CRITICAL: Use file session driver for E2E (array does NOT persist between requests)
    SESSION_DRIVER: 'file',
    SESSION_LIFETIME: '120',
    SESSION_SECURE_COOKIE: 'false',
    // Normalize app URL for consistent cookies in tests
    APP_URL: 'http://127.0.0.1:8000',
    // Cache must also be file-based for consistency
    CACHE_STORE: 'file',
    // PostgreSQL configuration
    DB_CONNECTION: 'pgsql',
    DB_HOST: '127.0.0.1',
    DB_PORT: isCI ? '5432' : '5434', // CI uses default port, local uses 5434
    DB_DATABASE: 'mercach_test',
    DB_USERNAME: 'postgres',
    DB_PASSWORD: 'postgres',
};

// Build server command based on environment
// CI: Database is prepared by workflow, just start server
// Local: Prepare database and start server
// Use 'php artisan serve' for better session handling
const serverCommand = isCI
    ? 'php artisan config:clear && php artisan serve --host=127.0.0.1 --port=8000'
    : 'php artisan config:clear && php artisan cache:clear && ' +
      'php artisan migrate:fresh --seed --force && ' +
      'php artisan serve --host=127.0.0.1 --port=8000';

const webServers = [
    {
        command: serverCommand,
        url: 'http://127.0.0.1:8000/login',
        reuseExistingServer: !isCI,
        timeout: 600_000,
        // Pass environment variables directly to PHP process
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
        // Admin role - full permissions
        {
            name: 'chromium-admin',
            use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/state.admin.json' },
            dependencies: ['setup'],
        },
        {
            name: 'firefox-admin',
            use: { ...devices['Desktop Firefox'], storageState: 'tests/e2e/state.admin.json' },
            dependencies: ['setup'],
        },
        // Consultoría Jurídica role - contracts, concessionaires, locals (view)
        {
            name: 'chromium-consultoria',
            use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/state.consultoria.json' },
            dependencies: ['setup'],
        },
        {
            name: 'firefox-consultoria',
            use: { ...devices['Desktop Firefox'], storageState: 'tests/e2e/state.consultoria.json' },
            dependencies: ['setup'],
        },
        // Portal user (cesionario autoservicio)
        {
            name: 'chromium-portal',
            use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/state.portal.json' },
            dependencies: ['setup'],
        },
        {
            name: 'firefox-portal',
            use: { ...devices['Desktop Firefox'], storageState: 'tests/e2e/state.portal.json' },
            dependencies: ['setup'],
        },
        // Future: gestor-cobranza role
        // {
        //     name: 'chromium-cobranza',
        //     use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/state.cobranza.json' },
        //     dependencies: ['setup'],
        // },
    ],
});
