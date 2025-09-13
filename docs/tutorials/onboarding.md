---
title: 'Onboarding (Tutorial)'
summary: 'Primeros pasos: preparar entorno local (backend, frontend y pruebas) replicando lo que valida la CI.'
icon: material/rocket-launch
tags:
    - tutorial
    - onboarding
    - entorno
---

# Onboarding (Tutorial)

Objetivo: dejar el entorno listo (backend + frontend + pruebas) replicando lo que valida la CI.

## Prerrequisitos

- PHP 8.2+, Composer 2.x
- Node.js 20+ y npm
- PostgreSQL (local por defecto 5432; pruebas usan `.env.testing` con 5434)

## Backend

```bash
# Variables de entorno
cp .env.example .env
php artisan key:generate

# Dependencias PHP
composer install

# Migraciones y seeders (BD local por defecto en 5432)
php artisan migrate --seed
```

## Frontend

```bash
npm ci
npm run build
```

## Pruebas (testing)

- Fuente única: `.env.testing` (Postgres `127.0.0.1:5434`, DB `boilerplate_laravel12_test`).

```bash
php artisan migrate --env=testing
vendor/bin/pest
```

## Verificación local (antes de commitear)

```bash
npm run lint:ci && npm run typecheck && npm run format:check \
  && composer run analyse && vendor/bin/pint -n --test \
  && npm run build && php artisan test -q
```

## CI/CD

- Ver guía: [CI/CD y Chequeos Locales](../ci-cd.md)

## Troubleshooting

- Si no ves el botón "copy" en snippets, prueba desactivar temporalmente `navigation.instant` en `mkdocs.yml`.
- Al mover páginas, agrega redirects en `mkdocs.yml` (plugin `redirects`).
