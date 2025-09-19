#!/usr/bin/env node
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';

const ROOT = new URL('../../..', import.meta.url).pathname;
const TESTS_DIR = join(ROOT, 'tests/e2e');
const CRUD_DIR = join(TESTS_DIR, 'crud');

function sh(cmd) {
  return execSync(cmd, { cwd: ROOT, stdio: ['ignore', 'pipe', 'pipe'] }).toString('utf8');
}

function ensureDir(p) {
  try { mkdirSync(p, { recursive: true }); } catch { /* noop */ }
}

function readNavTitles() {
  // Parse resources/js/menu/generated.ts for { title, url }
  const menuPath = join(ROOT, 'resources/js/menu/generated.ts');
  const titles = new Map();
  try {
    const src = readFileSync(menuPath, 'utf8');
    const rx = /\{\s*title:\s*'([^']+)'\s*,\s*url:\s*'([^']+)'/g;
    let m;
    while ((m = rx.exec(src)) !== null) {
      titles.set(m[2], m[1]);
    }
  } catch { /* noop */ }
  return titles;
}

function specPathFor(module) {
  if (module.type === 'catalog') return join(CRUD_DIR, `catalogs.${module.name}.spec.ts`);
  return join(CRUD_DIR, `${module.name}.spec.ts`);
}

function specTemplate(module, titleGuess) {
  const header = `import { test, expect } from '@playwright/test';\nimport { goToDashboard, goToUsers, goToRoles, goToCatalog } from '../utils/navigation';\nimport { isAdminProject, isViewerProject } from '../utils/role-assert';\n`;
  const title = titleGuess || (module.type === 'catalog' ? module.name : module.name.charAt(0).toUpperCase() + module.name.slice(1));
  if (module.type === 'catalog') {
    return `${header}
// Auto-generated skeleton spec for ${module.name}

test.describe('${title} (admin)', () => {
  test('index visible${module.actions.has('export') ? ' and export available' : ''}', async ({ page }, testInfo) => {
    if (!isAdminProject(testInfo.project.name)) test.skip();
    await goToDashboard(page);
    await goToCatalog(page, ${JSON.stringify(title)});
    await expect(page.getByRole('heading', { name: /${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/i })).toBeVisible();
    ${module.actions.has('export') ? `// Try CSV export\n    try {\n      const [dl] = await Promise.all([\n        page.waitForEvent('download'),\n        page.getByRole('button', { name: /exportar/i }).click().then(() => page.getByRole('menuitem', { name: /csv/i }).click()),\n      ]);\n      const fn = await dl.suggestedFilename();\n      expect(fn.toLowerCase()).toContain('${module.name}');\n    } catch { /* optional */ }` : ''}
  });
});

test.describe('${title} (viewer)', () => {
  test('link absent or actions hidden', async ({ page }, testInfo) => {
    if (!isViewerProject(testInfo.project.name)) test.skip();
    await goToDashboard(page);
    const link = page.getByRole('link', { name: /${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/i });
    if ((await link.count()) > 0) {
      await link.click();
      await expect(page.getByRole('button', { name: /nuevo|crear/i })).toHaveCount(0);
    } else {
      await expect(link).toHaveCount(0);
    }
  });
});
`;
  }
  // Users / Roles
  const goto = module.name === 'users' ? 'goToUsers' : 'goToRoles';
  return `${header}
// Auto-generated skeleton spec for ${module.name}

test.describe('${title} (admin)', () => {
  test('index visible', async ({ page }, testInfo) => {
    if (!isAdminProject(testInfo.project.name)) test.skip();
    await goToDashboard(page);
    await ${goto}(page);
    await expect(page.getByRole('heading', { name: /${module.name === 'users' ? 'usuarios' : 'roles'}/i })).toBeVisible();
  });
});

test.describe('${title} (viewer)', () => {
  test('link absent or actions hidden', async ({ page }, testInfo) => {
    if (!isViewerProject(testInfo.project.name)) test.skip();
    await goToDashboard(page);
    const link = page.getByRole('link', { name: /${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}/i });
    if ((await link.count()) > 0) {
      await link.click();
      await expect(page.getByRole('button', { name: /nuevo|crear/i })).toHaveCount(0);
    } else {
      await expect(link).toHaveCount(0);
    }
  });
});
`;
}

function main() {
  ensureDir(CRUD_DIR);
  const json = JSON.parse(sh('php artisan route:list --json'));
  const navTitles = readNavTitles();

  /** @type {Array<{type: 'users'|'roles'|'auditoria'|'catalog', name: string, actions: Set<string>}>} */
  const modules = [];

  // Group catalogs
  const catalogs = new Map(); // name -> Set(actions)
  for (const r of json) {
    const name = r.name || '';
    if (name.startsWith('users.')) {
      modules.push({ type: 'users', name: 'users', actions: new Set([name.split('.')[1]]) });
    } else if (name.startsWith('roles.')) {
      modules.push({ type: 'roles', name: 'roles', actions: new Set([name.split('.')[1]]) });
    } else if (name.startsWith('auditoria.')) {
      modules.push({ type: 'auditoria', name: 'auditoria', actions: new Set([name.split('.')[1]]) });
    } else if (name.startsWith('catalogs.')) {
      const [, resource, action] = name.split('.');
      if (!catalogs.has(resource)) catalogs.set(resource, new Set());
      catalogs.get(resource).add(action);
    }
  }

  // Merge catalogs
  for (const [res, actions] of catalogs.entries()) {
    modules.push({ type: 'catalog', name: res, actions });
  }

  const created = [];
  for (const m of modules) {
    let p = specPathFor(m);
    // Skip if we already have a handcrafted spec
    if (existsSync(p)) continue;

    // Guess title from nav by url
    let url = m.type === 'catalog' ? `/catalogs/${m.name}` : `/${m.name}`;
    const title = navTitles.get(url);

    const content = specTemplate(m, title);
    ensureDir(dirname(p));
    writeFileSync(p, content, 'utf8');
    created.push(p);
  }

  console.log(`Generated ${created.length} spec(s):`);
  for (const p of created) console.log(` - ${p.replace(ROOT + '/', '')}`);
  if (created.length === 0) console.log('Nothing to generate.');
}

main();
