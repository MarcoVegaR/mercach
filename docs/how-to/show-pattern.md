---
title: 'Cómo aplicar el patrón Show'
summary: 'Guía práctica paso a paso para implementar el patrón Show de extremo a extremo (Backend + Frontend) en Laravel 12 + Inertia + React, con soporte para relaciones, counts y partial reloads.'
icon: material/eye-outline
tags:
    - how-to
    - backend
    - frontend
    - inertia
    - react
    - laravel
---

# Cómo aplicar el patrón Show

Esta guía consolida el antiguo contenido de Backend y Frontend en un único documento práctico. Verás cómo implementar Show desde el controlador y el servicio hasta la página Inertia con React y los hooks necesarios.

!!! note "Qué problema resuelve"
El patrón Show estandariza cómo cargar un recurso individual, controlando qué relaciones y conteos se permiten y ofreciendo una respuesta consistente para la UI (`item` + `meta`).

## Requisitos previos

- Laravel 12 con el sistema de Repositorios y Servicios del proyecto
- Inertia.js + React + TypeScript
- Policies registradas (recuerda registrar `AuthServiceProvider` en `bootstrap/providers.php`)

## Backend

### 1) ShowQuery DTO y BaseShowRequest

```php
use App\DTO\ShowQuery;

$query = new ShowQuery(
    with: ['permissions'],      // Relaciones a cargar
    withCount: ['permissions'], // Relaciones a contar
    append: [],                 // Atributos calculados
    withTrashed: false          // Incluir soft deletes
);
```

Ejemplo de FormRequest que whitelist-ea parámetros:

```php
// app/Http/Requests/RoleShowRequest.php
class RoleShowRequest extends BaseShowRequest
{
    protected function allowedRelations(): array
    {
        return ['permissions'];
    }

    protected function allowedCounts(): array
    {
        return ['permissions'];
    }
}
```

### 2) Repositorio y Servicio

El repositorio expone métodos `showById`/`showByUuid` y aplica hooks de relaciones/filters si procede.

```php
// $role = $repository->showById($id, $query);
// $role = $repository->showByUuid($uuid, $query);
```

El servicio devuelve una estructura estable para la UI:

```php
$data = $service->showById($id, $query);
// Retorna:
// [
//   'item' => [...],  // Datos del recurso
//   'meta' => [...]   // Metadata (p. ej., loaded_relations)
// ]
```

Hook útil para transformar el item:

```php
protected function toItem(\Illuminate\Database\Eloquent\Model $model): array
{
    return [
        'id' => $model->id,
        'name' => $model->name,
        'display_name' => ucfirst($model->name),
        // ...campos adicionales
    ];
}
```

### 3) Controlador con HandlesShow

```php
// app/Http/Controllers/RolesController.php
class RolesController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesShow;

    protected function showRequestClass(): string
    {
        return \App\Http\Requests\RoleShowRequest::class;
    }

    protected function showView(): string
    {
        return 'roles/show';
    }
}
```

Rutas:

```php
// routes/roles.php
Route::get('/roles/{role}', [RolesController::class, 'show'])->name('roles.show');
```

### 4) Autorización y pruebas

- Policy: método `view`
- En el controlador se autoriza con `$this->authorize('view', $model)`

Prueba mínima:

```php
class RoleShowTest extends TestCase
{
    public function test_show_returns_role_with_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('roles.view');

        $role = Role::create(['name' => 'test']);

        $response = $this->actingAs($user)
            ->get(route('roles.show', [
                $role,
                'with' => ['permissions']
            ]));

        $response->assertOk();
    }
}
```

!!! tip "Evita N+1"
Define `allowedRelations()` y usa eager loading controlado. Calcula conteos peligrosos (como `users_count` de Roles) vía subselects en el repositorio cuando la relación dependa de configuración (p. ej., `guard_name`).

## Frontend (React + Inertia)

### Componentes base

- `ShowLayout`: grid responsivo con header, actions y aside sticky
- `ShowSection`: secciones con estado de `loading` y `skeleton`
- `SectionNav`: navegación lateral con scroll-spy accesible

### Hook `useShow`

Gestiona el estado del Show: partial reloads, pestañas activas y carga perezosa de relaciones.

```tsx
// pages/roles/show.tsx
import { useShow } from '@/hooks/use-show';
import { ShowLayout } from '@/components/show-base/ShowLayout';
import { ShowSection } from '@/components/show-base/ShowSection';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';

export default function RoleShow({ item: initialItem, meta: initialMeta }) {
    const { item, meta, loading, activeTab, setActiveTab, loadPart } = useShow({
        endpoint: `/roles/${initialItem.id}`,
        initialItem,
        initialMeta,
    });

    const handleTabChange = (value: string) => {
        setActiveTab(value);
        if (value === 'permissions' && !meta.loaded_relations?.includes('permissions')) {
            loadPart({ with: ['permissions'], withCount: ['permissions'] });
        }
    };

    return (
        <ShowLayout header={<h1>{item.name}</h1>} actions={<Button>Editar</Button>} aside={<Card>Resumen</Card>}>
            <Tabs value={activeTab} onValueChange={handleTabChange}>
                <TabsList>
                    <TabsTrigger value="overview">Resumen</TabsTrigger>
                    <TabsTrigger value="permissions">Permisos</TabsTrigger>
                </TabsList>
                <TabsContent value="overview">
                    <ShowSection id="overview" title="Información básica">
                        {/* Contenido */}
                    </ShowSection>
                </TabsContent>
                <TabsContent value="permissions">
                    <ShowSection id="permissions" title="Permisos" loading={loading}>
                        {/* Contenido */}
                    </ShowSection>
                </TabsContent>
            </Tabs>
        </ShowLayout>
    );
}
```

### Buenas prácticas

- Cargar relaciones pesadas cuando el usuario lo solicite (tabs)
- Usar `only: ['item','meta']` en partial reloads para eficiencia
- Mantener accesibilidad: skip links, gestión de foco, ARIA

### Checklist de pruebas (UI)

- [ ] Partial reloads correctos (solo `item`/`meta`)
- [ ] Persistencia de pestaña activa
- [ ] Skeletons/estado de carga visibles
- [ ] Navegación por teclado y SR-friendly

## API de ejemplo y respuesta

```http
GET /roles/1
GET /roles/1?with[]=permissions
GET /roles/1?with[]=permissions&withCount[]=permissions
```

```json
{
    "item": {
        "id": 1,
        "name": "admin",
        "guard_name": "web",
        "permissions": [],
        "permissions_count": 5
    },
    "meta": {
        "loaded_relations": ["permissions"],
        "loaded_counts": ["permissions_count"]
    }
}
```

## Migración desde controladores legacy

```php
// Antes
public function show(Role $role)
{
    $role->load('permissions');
    return Inertia::render('Roles/Show', [
        'role' => $role
    ]);
}

// Después
use HandlesShow;
protected function showRequestClass(): string { return RoleShowRequest::class; }
protected function showView(): string { return 'roles/show'; }
```

## Solución de problemas

- "Relation not allowed": añade a `allowedRelations()`
- 403 inesperado: verifica permisos y que `AuthServiceProvider` esté registrado en `bootstrap/providers.php`
- "Missing counts": whitelist en `allowedCounts()` y verifica la relación

## Recursos relacionados

- `docs/backend/requests.md` (Index/filters → ListQuery)
- `docs/backend/services.md` y `docs/backend/repositories.md`
- `docs/reference/accessibility.md` y `docs/reference/performance.md`
