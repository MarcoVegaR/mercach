---
title: 'CI/CD y Chequeos Locales'
summary: 'Qué valida la CI (lint, typecheck, build y tests) y cómo replicarlo localmente, además de servir/compilar la documentación con MkDocs.'
icon: material/robot
tags:
    - ci
    - calidad
    - documentación
---

# CI/CD y Chequeos Locales

Esta guía resume qué valida la CI y cómo replicarlo localmente antes de commitear.

## Workflows

- `/.github/workflows/lint.yml`
    - PHP Pint (`vendor/bin/pint -n --test`)
    - Prettier (`npm run format:check`)
    - ESLint (`npm run lint:ci`)
    - TypeScript (`npm run typecheck`)
    - PHPStan (`composer run analyse`)
- `/.github/workflows/tests.yml`
    - Instala dependencias Node y compila assets (`npm ci && npm run build`).
    - Instala dependencias PHP (`composer install`).
    - Migra BD Postgres (puerto 5432 en CI).
    - Ejecuta tests `./vendor/bin/pest`.

## Cómo testear localmente antes del commit

1. **Instalar dependencias**

    ```bash
    composer install
    npm ci
    ```

2. **Formateo y linters**

    ```bash
    # PHP style (no modifica archivos, solo verifica)
    vendor/bin/pint -n --test

    # Frontend
    npm run format:check   # Prettier
    npm run lint:ci        # ESLint
    npm run typecheck      # TypeScript
    ```

3. **Análisis estático PHP**

    ```bash
    composer run analyse   # PHPStan
    ```

4. **Tests**
    - Asegúrate de que Postgres de pruebas esté accesible según `.env.testing`.
        - Host: `127.0.0.1`
        - Puerto: `5434` (local, ver nota abajo)
        - DB: `boilerplate_laravel12_test`
    ```bash
    cp .env.testing .env   # Para lanzar server local si hace falta
    php artisan key:generate
    php artisan migrate --env=testing
    vendor/bin/pest
    ```

## Documentación (MkDocs) — desarrollo local

1. Crear y activar venv de Python (opcional pero recomendado):

```bash
python -m venv .venv && source .venv/bin/activate
```

2. Instalar dependencias de documentación (versionadas):

```bash
pip install -r docs/requirements.txt
```

3. Servir o compilar documentación:

```bash
mkdocs serve -a 127.0.0.1:8002   # hot reload
mkdocs build --strict            # build estricto
```

Alternativamente (si mkdocs está en PATH):

```bash
npm run docs:serve
npm run docs:build
```

Notas:

- Puedes crear `mkdocs.local.yml` para overrides locales (p. ej., `site_url` y/o desactivar `redirects` en local). Ya está en `.gitignore`.
- No commitees `site/` (salida de build) ni el venv `.venv/`.

## Notas de entorno

- **DB de pruebas**: seguimos usando `.env.testing` como fuente única de configuración. En local el puerto es `5434`. En CI (GitHub Actions) el servicio expone `5432`.
- **Locales**: por defecto `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es`, `APP_FAKER_LOCALE=es_ES`.
- **Pre-commit**: Husky ejecuta `lint-staged` para formatear/lintar archivos tocados; Pint se ejecuta sobre archivos PHP staged.

## Errores comunes

- Pint: `unary_operator_spaces` → elimina el espacio después de `!`, por ejemplo: `!array_key_exists(...)`.
- ESLint/TS: si fallan imports de alias, revisa `tsconfig.json` y `vite.config.js`.
