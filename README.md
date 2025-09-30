# Merca Chacao — Sistema administrativo (Laravel 12 + React/Inertia)

Aplicación administrativa para la gestión de locales del Mercado de Chacao. Construida con Laravel 12, Inertia (React + TypeScript) y Vite.

## Documentación

Este README es intencionalmente breve. La guía completa (Manual de Usuario, Procesos de negocio y Guía Técnica) vive en la web de documentación:

- Sitio de documentación (MkDocs): https://marcovegar.github.io/mercach
- Fuentes de la documentación: directorio `docs/`

Para temas específicos consulta la navegación del sitio. Ejemplos:

- CI/CD: https://marcovegar.github.io/mercach/ci-cd/
- Contribución: https://marcovegar.github.io/mercach/contributing/

## Requisitos

- PHP 8.2+ (testeado en 8.3)
- Composer 2.x
- Node.js 20+ y npm

## Instalación mínima

```bash
# Variables de entorno y clave
cp .env.example .env
php artisan key:generate

# Dependencias
composer install
npm install

# Base de datos (PostgreSQL por defecto)
php artisan migrate

# Build inicial de frontend (una vez)
npm run build
```

## Desarrollo local

```bash
# Levanta Laravel + Vite + Queue + Logs
composer run dev
```

- App: http://127.0.0.1:8000
- Vite con recarga en caliente mediante `laravel-vite-plugin`.

## Enlaces útiles

- Documentación (inicio): https://marcovegar.github.io/mercach
- CI/CD (docs): https://marcovegar.github.io/mercach/ci-cd/
- Contribución (docs): https://marcovegar.github.io/mercach/contributing/

## Alcance del README

Para mantenerlo breve, la siguiente información está documentada en los Docs y no se repite aquí:

- Permisos y roles (Spatie), políticas y gates
- Tema y tokens (shadcn/ui — Supabase)
- Convenciones de commits y flujo de ramas
- Estructura del proyecto y scripts útiles
- Pipelines de CI/calidad y versionado (semantic-release)
- Guías paso a paso (Index, Show, filtros, DataTable, etc.)

## Contribuir

PRs bienvenidas. Consulta la guía de contribución en la documentación: https://marcovegar.github.io/boilerplate-laravel12/contributing/

## Licencia

MIT — ver [LICENSE](LICENSE).

## Seguridad

Si encuentras una vulnerabilidad, por favor abre un Issue privado (o un Security Advisory en GitHub cuando esté habilitado). No publiques detalles de explotación antes de un parche.

## Dashboard v1

### Endpoints (BFF)

- `GET /dashboard` (Inertia) — protegido con `permission:dashboard.view`.
- `GET /api/dashboard/kpis` — protegido con `permission:dashboard.view.cards`.
- `GET /api/dashboard/locales-disponibles-distribucion?by=local_type_id` — protegido con `permission:dashboard.view.charts`.

### Permisos

Se agregan automáticamente vía `config/permissions/dashboard.php` y `Database\Seeders\PermissionsSeeder`:

- `dashboard.view`
- `dashboard.view.cards`
- `dashboard.view.charts`
- `dashboard.view.table`

En frontend, `auth.can` oculta secciones si faltan permisos, pero la autoridad es el backend.

### KPIs y gráfico

- KPI Cards cargan desde `/api/dashboard/kpis` usando TanStack Query (`staleTime: 60s`). Botón "Refrescar" hace `invalidateQueries`.
- Donut "Locales disponibles por tipo" usa `/api/dashboard/locales-disponibles-distribucion`.
    - Slices con `dataKey=value`, `nameKey=label` y colores `--chart-1..5`.
    - Tooltip/legend estilo shadcn.
    - Click en slice navega al índice de locales filtrando por `local_type_id` y estado Disponible (DISP).

### Fuente de verdad

- "Contrato vigente": `status(code) = 'VIG' AND start_date <= today AND (end_date IS NULL OR end_date >= today)`.
- "Local disponible": NOT EXISTS contrato vigente (regla canónica). Si existe `local_status = 'DISP'`, se usa solo como atajo visual en filtros.

### Performance

- Habilitado `pg_trgm` (si permitido) y agregados índices:
    - `contract_local (contract_id)`, `(local_id)`
    - `concessionaire_contract (concessionaire_id)`, `(contract_id)`
    - `locals (market_id)`, `(local_status_id)`

### Cómo correr

1. Instalar dependencias JS y PHP:

```bash
npm install
composer install
```

2. Migrar y seed (agrega permisos y catálogos mínimos):

```bash
php artisan migrate:fresh --seed
```

3. Desarrollo:

```bash
composer run dev
```

Abre http://127.0.0.1:8000 y asigna permisos a tu usuario para ver el dashboard y los gráficos.
