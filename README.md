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
