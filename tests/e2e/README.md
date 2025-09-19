# Playwright E2E for Merca Chacao

This suite verifies the main CRUD modules (Users, Roles, Catalogs) and Auditoría using Playwright with persisted login for two roles: admin and viewer.

## Requirements

- Node 18+
- PHP 8.2+
- SQLite/MySQL/PostgreSQL configured in `.env` (the suite runs `migrate:fresh --seed` before E2E)
- Ports 8000 and 5173 available

Admin and viewer test users are seeded by `UsersSeeder`:

- Admin: `test@mailinator.com` / `12345678`
- Viewer: `viewer@mailinator.com` / `12345678`

If 2FA is enabled, set `E2E_2FA_CODE` in your environment for the admin to complete the challenge during setup.

## How to run

1. Install dependencies and browsers (already handled by the initializer):

```bash
npm install
npx playwright install
```

2. Run the E2E tests (this will also run `php artisan migrate:fresh --seed` first):

```bash
npm run test:e2e
```

This will:

- Start Laravel: `php artisan serve --host=127.0.0.1 --port=8000`
- Start Vite dev server: `npm run dev`
- Run the Playwright projects:
    - `setup` (logs in as admin and viewer and saves storage state)
    - `chromium-admin` (tests as admin)
    - `chromium-viewer` (tests as viewer)

3. UI mode (debugging):

```bash
npm run test:e2e:ui
```

4. Always-on traces for a run:

```bash
npm run test:e2e:trace
```

5. Codegen:

```bash
npm run codegen
```

## Viewing reports and traces

- HTML report opens automatically at the end of the run. Reopen:

```bash
npx playwright show-report
```

- For a single test failure, open its trace from the report (trace is captured `on-first-retry`).

## Structure

- Config: `playwright.config.ts` (servers for Laravel + Vite, baseURL `http://127.0.0.1:8000`, trace `on-first-retry`).
- Setup: `tests/e2e/auth.setup.ts` (persists sessions to `tests/e2e/state.admin.json` and `tests/e2e/state.viewer.json`).
- Utilities: `tests/e2e/utils/` (navigation helpers and role assertions).
- Specs:
    - Smoke: `tests/e2e/smoke.spec.ts`
    - Auditoría: `tests/e2e/auditoria.spec.ts`
    - CRUD (admin + viewer visibility):
        - `tests/e2e/crud/users.spec.ts`
        - `tests/e2e/crud/roles.spec.ts`
        - `tests/e2e/crud/catalogs.local-type.spec.ts`
        - `tests/e2e/crud/catalogs.document-type.spec.ts`

## Auto-generation for more CRUDs

Use the generator to scaffold skeleton specs for all detected modules from `php artisan route:list --json` and the sidebar menu titles in `resources/js/menu/generated.ts`:

```bash
npm run test:e2e:gen
```

This writes any missing `tests/e2e/crud/*.spec.ts` files without overwriting existing ones.

## Environment variables

- `E2E_EMAIL_ADMIN`, `E2E_PASSWORD_ADMIN` (defaults to seeded admin)
- `E2E_EMAIL_VIEWER`, `E2E_PASSWORD_VIEWER` (defaults to seeded viewer)
- `E2E_2FA_CODE` for Fortify TOTP challenge (optional)

## Notes

- Selectors are based on roles and labels: `getByRole`, `getByLabel`, and robust `getByText` with regex.
- The viewer suite tolerates links being completely hidden (per permissions) or actions being invisible/disabled.
- If a test fails, open the HTML report and the trace to refine selectors.
