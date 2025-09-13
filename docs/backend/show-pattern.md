---
title: 'Patrón Show (contenido movido)'
summary: 'Esta página fue fusionada en una guía única How-to que cubre Backend y Frontend.'
icon: material/eye-off-outline
tags:
    - how-to
    - redirect
    - backend
---

# Patrón Show (contenido movido)

Esta página ha sido consolidada en una guía única: [Cómo aplicar el patrón Show](../how-to/show-pattern.md).

!!! note "Redirección"
Si llegaste aquí desde un enlace antiguo, la nueva ubicación es `how-to/show-pattern.md`. La navegación se actualizó para reflejar Diátaxis.

El patrón Show proporciona una forma estructurada y reutilizable de mostrar recursos individuales con soporte para eager loading, counts, y soft deletes. Sigue las convenciones del proyecto y se alinea con el patrón Index existente.

## Componentes

### 1. ShowQuery DTO

```php
use App\DTO\ShowQuery;

$query = new ShowQuery(
    with: ['permissions'],          // Relaciones a cargar
    withCount: ['permissions'],     // Relaciones a contar
    append: [],                     // Atributos a añadir
    withTrashed: false             // Incluir soft deleted
);
```

### 2. BaseShowRequest

Request base que valida y convierte parámetros a ShowQuery:

```php
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

### 3. Repository Methods

BaseRepository incluye métodos show:

```php
// Show por ID con parámetros
$role = $repository->showById($id, $query);

// Show por UUID con parámetros
$role = $repository->showByUuid($uuid, $query);
```

### 4. Service Methods

BaseService retorna estructura consistente:

```php
$data = $service->showById($id, $query);
// Returns:
// [
//     'item' => [...],  // Datos del recurso
//     'meta' => [...]   // Metadata
// ]
```

### 5. HandlesShow Trait

Trait para controladores que maneja el ciclo completo:

```php
class RolesController extends BaseController
{
    use HandlesShow;

    protected function showRequestClass(): string
    {
        return RoleShowRequest::class;
    }

    protected function showView(): string
    {
        return 'roles/show';
    }
}
```

## Implementación Ejemplo: Roles

### 1. Request

```php
// app/Http/Requests/RoleShowRequest.php
class RoleShowRequest extends BaseShowRequest
{
    protected function allowedRelations(): array
    {
        return ['permissions'];
    }
}
```

### 2. Controller

```php
// app/Http/Controllers/RolesController.php
class RolesController extends BaseIndexController
{
    use HandlesShow;

    protected function showRequestClass(): string
    {
        return RoleShowRequest::class;
    }

    protected function showView(): string
    {
        return 'roles/show';
    }
}
```

### 3. Routes

```php
// routes/roles.php
Route::get('/roles/{role}', [RolesController::class, 'show'])
    ->name('roles.show');
```

## API Request Examples

### Basic Show

```http
GET /roles/1
```

### With Relations

```http
GET /roles/1?with[]=permissions
```

### With Counts

```http
GET /roles/1?withCount[]=permissions
```

### Combined

```http
GET /roles/1?with[]=permissions&withCount[]=permissions
```

## Response Structure

```json
{
    "item": {
        "id": 1,
        "name": "admin",
        "guard_name": "web",
        "permissions": [...],
        "permissions_count": 5
    },
    "meta": {
        "loaded_relations": ["permissions"],
        "loaded_counts": ["permissions_count"]
    }
}
```

## Authorization

- Uses Policies con método `view`
- Controller llama `$this->authorize('view', $model)`
- Requiere permiso `{resource}.view`

## Testing

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

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('item.permissions')
                ->has('meta.loaded_relations')
            );
    }
}
```

## Hooks y Extensibilidad

### Service Hooks

```php
class RoleService extends BaseService
{
    // Customizar transformación del item
    protected function toItem(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'display_name' => ucfirst($model->name),
            // ... custom fields
        ];
    }

    // Añadir metadata personalizada
    protected function getShowMeta(Model $model, ShowQuery $query): array
    {
        $meta = parent::getShowMeta($model, $query);
        $meta['can_edit'] = auth()->user()->can('update', $model);
        return $meta;
    }
}
```

### Repository Hooks

```php
class RoleRepository extends BaseRepository
{
    // Aplicar lógica adicional al query
    protected function applyShowQuery(Builder $builder, ShowQuery $query): Builder
    {
        $builder = parent::applyShowQuery($builder, $query);

        // Añadir lógica custom
        $builder->where('is_active', true);

        return $builder;
    }
}
```

## Best Practices

1. **Whitelist Relations**: Siempre definir explícitamente las relaciones permitidas
2. **Avoid N+1**: Usar eager loading para relaciones necesarias
3. **Consistent Structure**: Mantener estructura `item` + `meta`
4. **Authorization First**: Verificar permisos antes de cargar datos
5. **Validation**: Validar todos los parámetros de entrada
6. **Idempotent**: Los métodos show deben ser idempotentes

## Migración desde Métodos Legacy

Si tienes métodos show existentes:

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

protected function showRequestClass(): string
{
    return RoleShowRequest::class;
}
```

## Troubleshooting

### Error: Relation not allowed

- Verificar que la relación esté en `allowedRelations()`
- Revisar el nombre exacto de la relación

### Error: 403 Forbidden

- Verificar que el usuario tenga permiso `{resource}.view`
- Confirmar que AuthServiceProvider esté registrado

### Missing counts

- Asegurar que la relación esté en `allowedCounts()`
- Verificar que el modelo tenga la relación definida
