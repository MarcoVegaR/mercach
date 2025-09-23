---
title: 'CHANGELOG de Documentación'
summary: 'Registro de cambios estructurales en la documentación: reorg Diátaxis, fusiones, redirects y normalización de estilo.'
icon: material/file-document-edit-outline
tags:
    - docs
    - changelog
    - diataxis
---

# CHANGELOG de Documentación

## 2025-09-23

### Solución definitiva al problema de Playwright CI y nueva funcionalidad

#### 🔧 Corrección crítica de Playwright en GitHub CI

- **Problema identificado**: Tests e2e fallan en CI por assets no compilados (patrones de títulos/headings no aparecen)
- **Solución implementada**:
    - Cambio en `playwright.yml`: `npm run build:ssr` → `npm run build`
    - Agregada verificación de assets compilados en workflow
    - Los assets SSR no son necesarios para Playwright, solo SPA build

#### ⚡ Nueva funcionalidad: Carga dinámica en páginas Show

- **Arquitectura mejorada**: Implementación consistente con patrones Inertia
- **Servicios actualizados**: Agregado método `loadShowData()` en:
    - `MarketService`
    - `LocalTypeService`
    - `LocalStatusService`
    - `LocalLocationService`
- **Interfaces actualizadas**: Métodos agregados a contratos de servicios
- **Hook `useShow` corregido**: Eliminado fetch API, solo `router.visit()` con `only: ['item', 'meta']`

#### 🧪 Tests comprehensivos agregados

- **12 nuevos tests** para funcionalidad show en 4 módulos de catálogos:
    - `test_show_displays_*_details`: Verifica renderizado básico
    - `test_show_loads_dynamic_data_with_query_params`: Prueba carga dinámica con `with`/`withCount`
    - `test_show_forbidden_without_view_permission`: Validación de autorización

#### 📚 Documentación actualizada

- **Expandida guía de Services** (`docs/backend/services.md`):
    - Nueva sección "Carga Dinámica de Datos (Show Pages)"
    - Documentación completa del patrón `loadShowData()`
    - Ejemplos de integración frontend/backend
    - Checklist actualizado con nuevos requisitos

#### ✅ Principios SOLID respetados

- **Lógica en servicios**: Controllers delegan a `serviceConcrete->loadShowData()`
- **Separación de responsabilidades**: Services manejan whitelist de relaciones y carga eficiente
- **Interfaces bien definidas**: Contratos claros para cada servicio

## 2025-09-06

### Cambios principales

- Unificación del patrón Show en una guía única How-to:
    - Nueva: `docs/how-to/show-pattern.md` (Backend + Frontend, con checklist y ejemplos)
    - Las páginas antiguas quedan como stubs con nota de redirección:
        - `docs/backend/show-pattern.md`
        - `docs/frontend/show-pattern.md`
- Reorganización completa de la navegación según Diátaxis en `mkdocs.yml`:
    - Tutoriales → `tutorials/onboarding.md`
    - Cómo hacerlo → How-to de Show, permisos, módulo Index, validación de formularios, rate limiting, errores, DataTable meta-driven, Cloudflare Tunnel, y Testing
    - Referencia → Variables/permisos, localización, DataTable API, gestión de roles, accesibilidad, rendimiento, BaseIndexRequest, Módulos (Auditoría, Usuarios)
    - Explicaciones → Guías de arquitectura (Controllers, Repositories, Services, Policies, Logging/Context) y Auditoría vs logging
    - Contribución y CI/CD visibles desde la nav
- Configuración profesional de MkDocs (`mkdocs.yml`):
    - Tema Material (`language: es`) y features: `navigation.tabs`, `navigation.sections`, `navigation.instant`, `navigation.top`, `content.code.copy`, `content.action.edit`, `content.tabs.link`
    - Plugins: `search`, `tags`, `git-revision-date-localized` (timeago, con fecha de creación), `glightbox`, `i18n` (ES)
    - Markdown extensions: `admonition`, `attr_list`, `def_list`, `footnotes`, `toc` with permalink, `pymdownx.details`, `pymdownx.superfences`, `pymdownx.tabbed`, `pymdownx.keys`, `pymdownx.mark`, `pymdownx.highlight`, `md_in_html`
    - `site_description` y `edit_uri: edit/main/docs/`
    - Redirects conservados para páginas legacy: `permissions.md`, `auditing-logging.md`
- Actualización de dependencias de documentación (`docs/requirements.txt`):
    - `mkdocs>=1.6`, `mkdocs-material>=9.5`
    - `mkdocs-git-revision-date-localized-plugin>=1.2`, `mkdocs-glightbox>=0.3`
    - Se mantienen `mkdocs-static-i18n>=1.2`, `mkdocs-redirects>=1.2`, `pymdown-extensions>=10.8`
- Normalización de front-matter y homepage:
    - Front-matter agregado/ajustado en: `docs/index.md`, `docs/contributing.md` (nuevo), `docs/ci-cd.md`, `docs/reference/accessibility.md`, `docs/tutorials/onboarding.md`, `docs/how-to/show-pattern.md`
    - Títulos en español y resúmenes consistentes

### Notas de migración

- Navegación y estructura ya no usan `backend/` y `frontend/` como categorías principales; sus contenidos se redistribuyeron en Diátaxis.
- Los antiguos `show-pattern.md` siguen existiendo como stubs bibliográficos para no romper enlaces locales; opcionalmente se pueden eliminar y dejar redirects si se prefiere.

### Pendientes (TODO)

- Añadir front-matter consistente al resto de páginas incluidas en nav que aún no lo tienen (p. ej., `backend/rate-limiting.md`, `backend/errors.md`, `backend/flash-and-errors.md`, `reference/datatable-api.md`, `reference/performance.md`, `modules/*`, etc.).
- Homogeneizar títulos (Title Case breve) y traducir cabeceras en páginas heredadas con inglés residual (p. ej., Performance/DataTable API).
- Añadir iconos por página de acuerdo al contenido (Material Icons — prefijo `material/`).
- Añadir tags por categoría (how-to, referencia, explicación, tutorial, backend, frontend, inertia, react, laravel, etc.).
- Agregar ejemplos con tabs (Linux/Windows, npm/yarn) donde apliquen.

---

Si detectas enlaces rotos o páginas sin icon/tags, abre un issue o PR siguiendo `docs/contributing.md`.
