---
title: 'Variables y permisos'
summary: 'Referencia de variables de entorno principales y agregador de permisos (config/permissions.php) con notas de uso en backend y frontend.'
icon: material/tune
tags:
    - referencia
    - configuración
    - permisos
---

# Referencia: Variables y permisos

## Variables de entorno (principales)

- Localización por defecto:
    - `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es`, `APP_FAKER_LOCALE=es_ES`
- Base de datos (desarrollo por defecto):
    - `DB_HOST=127.0.0.1`, `DB_PORT=5432`
- Testing (solo `.env.testing`):
    - `DB_HOST=127.0.0.1`, `DB_PORT=5434`, `DB_DATABASE=boilerplate_laravel12_test`, `DB_USERNAME=postgres`, `DB_PASSWORD=postgres`
- Logging:
    - `LOG_CHANNEL=stack`, `LOG_STACK=stderr`

## Permisos

- Declaración por módulo: `config/permissions/*.php`.
- Agregador: `config/permissions.php` expone:
    - `guard`, `permissions` (lista única), `descriptions` (mapa nombre => descripción).

### Obtener permisos en runtime

```php
$permissions = config('permissions.permissions');
$descriptions = config('permissions.descriptions');
```

### Frontend (Inertia)

- `auth.can` contiene todas las llaves de `config('permissions.permissions')` con booleanos por usuario.

## Notas

- Los tests usan `.env.testing` como fuente única (sin overrides en `phpunit.xml`).
- Si cambias permisos, limpia configuración y re-seed (`Database\\Seeders\\PermissionsSeeder`).
