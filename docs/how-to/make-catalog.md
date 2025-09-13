---
title: 'Generar catálogos (make:catalog)'
summary: 'Guía del generador de catálogos CRUD con Index/Export, formularios, permisos y frontend (Inertia + React), alineado al proyecto Mercach.'
icon: material/robot
tags:
    - how-to
    - generator
    - scaffolding
    - backend
    - frontend
---

# Generar catálogos (make:catalog)

El comando `php artisan make:catalog` crea un módulo de catálogo completo, listo para usar, siguiendo los patrones del proyecto Mercach:

- Backend: migración, modelo, repository + interface, service + interface, requests (Index/Store/Update), policy, controller, permisos.
- Frontend (Inertia + React): páginas `index`, `form`, `show`, `columns`, `filters` bajo `resources/js/pages/` (en minúsculas), con DataTable, filtros, acciones en bloque y exportación (CSV/XLSX/JSON).
- Infraestructura: rutas (en `routes/catalogs.php`), entrada de menú (en `resources/js/menu/generated.ts`), y bindings en `DomainServiceProvider`.

El resultado es un módulo consistente, con permisos y policies correctamente cableados, UI coherente con Roles/Usuarios y exportación lista.

> Tip: puedes ejecutar en modo "dry run" para ver el plan sin escribir archivos.

## Requisitos previos

- `bootstrap/providers.php` debe registrar `App\Providers\AuthServiceProvider::class` para que las Policies carguen. Si falta, las policies no se registran y `Gate` devolverá 403 aunque el usuario tenga permisos.
- Asegúrate de tener `DomainServiceProvider` habilitado (viene incluido por defecto en este proyecto) y que `config/permissions.php` agregue los permisos de `config/permissions/*.php`.
- Las páginas Inertia deben estar en `resources/js/pages/` con rutas en minúsculas (p. ej. `resources/js/pages/catalogs/tipo-documento/index.tsx`).

## Uso básico

```bash
php artisan make:catalog TipoDocumento \
  --fields="code:string:50:unique,name:string:120,active:boolean,sort_order:int:nullable" \
  --menu="Catálogos"
```

Opciones disponibles:

- `Name` (argumento): Nombre del modelo en StudlyCase. Ej.: `TipoDocumento`.
- `--fields`: Lista separada por comas: `nombre:tipo:args:flags`. Ej.: `code:string:50:unique`.
- `--menu`: Etiqueta del grupo en el sidebar para insertar la entrada.
- `--label`: Etiqueta singular para UI (usada en títulos, descripciones de permisos y, junto con `--label-plural`, en el menú). Ej.: `--label="Banco"`.
- `--label-plural`: Etiqueta plural para UI (usada en títulos de listados y en el menú). Si se omite, se pluraliza automáticamente `--label`.
- `--soft-deletes`: Agrega `deleted_at` y adapta unique a `withoutTrashed()` en reglas.
- `--uuid-route`: Usa `uuid` como route key y lo agrega al modelo y requests.
- `--force`: Sobrescribe archivos existentes del módulo.
- `--dry-run`: Muestra un resumen (archivos a crear/modificar) sin escribir.

### Sintaxis de --fields

- Tipos soportados: `string`/`varchar`, `int`/`integer`, `bigint`, `decimal(precision,scale)`, `boolean`/`bool`, `text`, `enum(A,B,C)` (mapeado como `string(50)` en BD).
- Flags: `nullable`, `unique`.
- Normalizaciones automáticas: `active` → `is_active`, `order` → `sort_order`.

Ejemplos:

```text
code:string:50:unique,name:string:120,active:boolean,sort_order:int:nullable
name:string:100,description:text:nullable
estado:enum(ACTIVO,INACTIVO)
precio:decimal:12,2:nullable
```

## Archivos generados y modificados

Se crean:

- `database/migrations/*_create_{tabla}_table.php`
- `app/Models/{Model}.php`
- `app/Contracts/Repositories/{Model}RepositoryInterface.php`
- `app/Repositories/{Model}Repository.php`
- `app/Contracts/Services/{Model}ServiceInterface.php`
- `app/Services/{Model}Service.php`
- `app/Http/Requests/{Model}IndexRequest.php`
- `app/Http/Requests/{Model}StoreRequest.php`
- `app/Http/Requests/{Model}UpdateRequest.php`
- `app/Policies/{Model}Policy.php`
- `app/Http/Controllers/{Model}Controller.php`
- `config/permissions/{snake}.php`
- `resources/js/pages/catalogs/{slug}/index.tsx`
- `resources/js/pages/catalogs/{slug}/columns.tsx`
- `resources/js/pages/catalogs/{slug}/filters.tsx`
- `resources/js/pages/catalogs/{slug}/form.tsx`
- `resources/js/pages/catalogs/{slug}/show.tsx`

Se modifican idempotentemente (entre marcadores):

- `routes/catalogs.php` (grupo de rutas con middleware de permisos por operación)
- `resources/js/menu/generated.ts` (entrada del menú con permiso `.view`)
- `app/Providers/DomainServiceProvider.php` (bindings repo y service)
- `app/Providers/AuthServiceProvider.php` (mapeo `Model => Policy`), para evitar 403 en `authorize()`

> Nota: las inserciones son idempotentes. Si eliminas manualmente líneas dentro de los marcadores, puedes re-ejecutar el comando para reinsertarlas.

## Convenciones de permisos y policies

- Se crea `config/permissions/{snake}.php` con permisos estándares: `.view`, `.create`, `.update`, `.delete`, `.restore`, `.forceDelete`, `.export`, `.setActive` bajo el prefijo `catalogs.{slug}`.
- La `Policy` extiende `BaseResourcePolicy` con `abilityPrefix = 'catalogs.{slug}'`.
- El middleware de rutas y la UI (botones/acciones) usan esos permisos.

## Frontend (Inertia + React)

- Páginas bajo `resources/js/pages/catalogs/{slug}/` con layout y espaciados alineados a Roles.
- Los formularios (`form.tsx`) y columnas (`columns.tsx`) se generan dinámicamente a partir de `--fields`:
    - Si defines `swift_bic:string:11:nullable`, se incluye el campo en el formulario y columna si aplica.
    - Si NO defines `name`, el formulario no lo pedirá y las columnas no lo mostrarán.
    - Si agregas `active:boolean` se mapea a `is_active` y se renderiza el toggle en edición.
- DataTable con filtros, ordenamiento, selección, acciones masivas y export.
- Botón “Nuevo”, acciones “Editar/Eliminar/Activar” condicionadas por `auth.can[...]` compartido en `HandleInertiaRequests`.

## Beneficios

- Consistencia total con módulos base (Roles/Usuarios): permisos, policies, requests y rutas.
- Previene 403 habituales: registra automáticamente la `Policy` en `AuthServiceProvider`.
- Productividad: un comando crea BE + FE + permisos + routing + menú.
- Idempotencia: inserciones en archivos existentes entre marcadores.
- Export con cabeceras amigables en español y layout moderno, listo para producción.

## Ejemplos

```bash
# Catálogo con campos típicos y menú "Catálogos"
php artisan make:catalog TipoDocumento \
  --fields="code:string:50:unique,name:string:120,active:boolean,sort_order:int:nullable" \
  --menu="Catálogos"

# Con UUID en rutas y soft deletes
php artisan make:catalog Producto \
  --fields="sku:string:20:unique,nombre:string:120,precio:decimal:12,2:nullable,activo:boolean" \
  --uuid-route \
  --soft-deletes \
  --menu="Catálogos"

# Con etiquetas en español para el menú y permisos
php artisan make:catalog Bank \
  --fields="code:string:20:unique,name:string:160,swift_bic:string:11:nullable,active:boolean,sort_order:int:nullable" \
  --menu="Catálogos" \
  --label="Banco" \
  --label-plural="Bancos"

php artisan make:catalog PhoneAreaCode \
  --fields="code:string:4:unique,active:boolean,sort_order:int:nullable" \
  --menu="Catálogos" \
  --label="Código de área" \
  --label-plural="Códigos de área"
```

## Solución de problemas

- 403 al editar/actualizar:
    - Verifica que `App\Providers\AuthServiceProvider` esté en `bootstrap/providers.php`.
    - Confirma que el usuario tiene `catalogs.{slug}.update`.
    - Si limpiaste rutas o menú, vuelve a ejecutar el generador con `--force`.
- No aparece en el menú:
    - Revisa `resources/js/menu/generated.ts` y los marcadores. El generador reinsertará si están presentes.
- Rutas faltantes:
    - Asegúrate de mantener los marcadores en `routes/catalogs.php`.
- Export falla:
    - Asegúrate de tener configurados los Exporters (`exporter.csv`, `exporter.xlsx`, `exporter.json`) en `DomainServiceProvider` (ya vienen).

## Flujo recomendado post-generación

1. Ejecuta migraciones y seeders:
    ```bash
    php artisan migrate
    php artisan db:seed
    ```
2. Inicia el server y prueba en `/catalogs/{slug}`.
3. Ajusta columnas/validaciones en los archivos generados si es necesario.

Con este generador, crear catálogos alineados al proyecto Mercach es cuestión de segundos, manteniendo calidad, consistencia y seguridad.
